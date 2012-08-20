<?php

/**
 * A query printer for charts series using the jqPlot JavaScript library.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 1.8
 *
 * @file SRF_jqPlotSeries.php
 * @ingroup SemanticResultFormats
 * @licence GNU GPL v2 or later
 *
 * @author mwjames 
 */
class SRFjqPlotSeries extends SMWResultPrinter {
	
	/**
	 * @see SMWResultPrinter::getName
	 */
	public function getName() {
		return wfMsg( 'srf-printername-jqplotseries' );
	}

	/**
	 * Returns an array with the numerical data in the query result.
	 *
	 *
	 * @param SMWQueryResult $result
	 * @param $outputMode
	 *
	 * @return string
	 */
	protected function getResultText( SMWQueryResult $result, $outputMode ) {

		// Init
		$i = 0;
		$numcount = 0;

		// Check layout type
		if ( $this->params['charttype'] === '' ) {
			return $result->addErrors( array( wfMsgForContent( 'srf-error-missing-layout' ) ) );
		}

		// Get data set
		$data = $this->getResultData( $result, $outputMode );

		// Check data availability
		if ( count( $data['series'] ) == 0 ) {
			return $result->addErrors( array( wfMsgForContent( 'srf-warn-empy-chart' ) ) );
		} else {
			return $this->getFormatOutput( $this->getFormatSettings( $this->getNumbersTicks( $data ) ) );
		}
	}

	/**
	 * Returns an array with the numerical data
	 *
	 * @since 1.8
	 *
	 * @param SMWQueryResult $result
	 * @param $outputMode
	 *
	 * @return array
	 */
	protected function getResultData( SMWQueryResult $res, $outputMode ) {
		$data = array();
		$data['series'] = array();

		while ( $row = $res->getNext() ) {
			// Loop over their fields (properties)
			$label = '';
			$i = 0;

			foreach ( $row as /* SMWResultArray */ $field ) {
				$i++;
				$rowNumbers = array();

				// Grouping by subject (page object) or property
				if ( $this->params['group'] === 'subject' ){
					$groupedBy = $field->getResultSubject()->getTitle()->getText();
				} else {
					$groupedBy = $field->getPrintRequest()->getLabel();
				}

				// Property label
				$property = $field->getPrintRequest()->getLabel();

				// First column property typeid
				$i == 1 ? $data['fcolumntypeid'] = $field->getPrintRequest()->getTypeID() : '';

				// Loop over all values for the property.
				while ( ( /* SMWDataValue */ $object = $field->getNextDataValue() ) !== false ) {

					if ( $object->getDataItem()->getDIType() == SMWDataItem::TYPE_NUMBER ) {
						$number =  $object->getNumber();

						// Checking against the row and in case the first column is a numeric
						// value it is handled as label with the remaining steps continue to work
						// as it were a text label
						// The first column container will not be part of the series container
						if ( $i == 1 ){
							$label = $number;
							continue;
						}

						if ( $label !== '' && $number >= $this->params['min'] ){

							// Reference array summarize all items per row
							$rowNumbers += array ( 'subject' => $label, 'value' => $number, 'property' => $property );

							// Store plain numbers for simpler handling
							$data['series'][$groupedBy][] = $number;
						}
					}elseif ( $object->getDataItem()->getDIType() == SMWDataItem::TYPE_TIME ){
						$label = $object->getShortWikiText();
					}else{
						$label = $object->getWikiValue();
					}
				}
				// Only for array's with numbers 
				if ( count( $rowNumbers ) > 0 ) {
					$data[$this->params['group']][$groupedBy][]= $rowNumbers;
				}
			}
		}
		return $data;
	}

	/**
	 * Data set sorting
	 *
	 * @since 1.8
	 *
	 * @param array $data label => value
	 *
	 *@return array
	 */
	private function getFormatSettings( $data ) {

		// Init
		$dataSet = array ();
		$grid = array ();

		// Available markers
		$marker = array ( 'circle', 'diamond', 'square', 'filledCircle', 'filledDiamond', 'filledSquare' );

		// Series colour(has to be null otherwise jqplot runs with a type error)
		$seriescolors = $this->params['chartcolor'] !== '' ? array_filter( explode( "," , $this->params['chartcolor'] ) ) : null;
		$mode = 'series';

		// Re-grouping
		foreach ( $data[$this->params['group']] as $rowKey => $row ) {
			$values= array();

			foreach ( $row as $key => $value ) {
				// Switch labels according to the group parameter
				$label = $this->params['grouplabel'] === 'property' ? $value['property'] : $value['subject'];
				$values[] = array ( $label , $value['value'] );
			}
			$dataSet[] = $values;
		}

		// Series plotting parameters
		foreach ( $data[$this->params['group']] as $key => $row ) {
			$series[] = array ('label' => $key,
			'xaxis' => 'xaxis', // xaxis could also be xaxis2 or ...
			'yaxis' => 'yaxis',
			'fill'  => $this->params['stackseries'],
			'showLine' => $this->params['charttype'] !== 'scatter',
			'showMarker' => true,
			'trendline' => array (
				'show' => in_array( $this->params['trendline'], array( 'exp', 'linear' ) ),
				'shadow' => $this->params['theme'] !== 'simple',
				'type' => $this->params['trendline'],
			),
			'markerOptions' => array (
				'style' => $marker[array_rand( $marker )],
				'shadow' => $this->params['theme'] !== 'simple'
			),
			'rendererOptions' => array ('barDirection' => $this->params['direction'] )
			);
		};

		// Basic parameters
		$parameters = array (
			'numbersaxislabel' => $this->params['numbersaxislabel'],
			'labelaxislabel'   => $this->params['labelaxislabel'],
			'charttitle'   => $this->params['charttitle'],
			'charttext'    => $this->params['charttext'],
			'theme'        => $this->params['theme'] ? $this->params['theme'] : null,
			'valueformat'  => $this->params['datalabels'] === 'label' ? '' : $this->params['valueformat'],
			'ticklabels'   => $this->params['ticklabels'],
			'highlighter'  => $this->params['highlighter'],
			'autoscale'    => false,
			'direction'    => $this->params['direction'],
			'smoothlines'  => $this->params['smoothlines'],
			'chartlegend'  => $this->params['chartlegend'] !== '' ? $this->params['chartlegend'] : 'none',
			'colorscheme'  => $this->params['colorscheme'] !== '' ? $this->params['colorscheme'] : null,
			'pointlabels'  => $this->params['datalabels'] === 'none' ? false : $this->params['datalabels'],
			'datalabels'   => $this->params['datalabels'],
			'stackseries'  => $this->params['stackseries'],
			'grid'         => $this->params['theme'] === 'vector' ? array ( 'borderColor' => '#a7d7f9' ) : ( $this->params['theme'] === 'simple' ? array ( 'borderColor' => '#ddd' ) : null ),
			'seriescolors' => $seriescolors
		);

		return array (
			'data'          => $dataSet,
			//'rawdata'      => $data , // control array
			'series'        => $series,
			'ticks'         => $data['numbersticks'],
			'total'         => $data['total'],
			'fcolumntypeid' => $data['fcolumntypeid'],
			'mode'          => $mode,
			'renderer'      => $this->params['charttype'],
			'parameters'    => $parameters
		);
	}

	/**
	 * Fetch numbers ticks
	 *
	 * @since 1.8
	 *
	 * @param array $data
	 */
	protected function getNumbersTicks( array $data ) {

		// Only look for numeric values that have been stored
		$numerics = array_values( $data['series'] );

		// Find min and max values to determine the graphs axis parameter
		$maxValue = count( $numerics ) == 0 ? 0 : max( array_map( "max", $numerics ) );

		if ( $this->params['min'] === false ) {
			$minValue = count( $numerics ) == 0 ? 0 : min( array_map( "min", $numerics ) );
		} else {
			$minValue = $this->params['min'];
		}

		// Get ticks info
		$data['numbersticks'] = SRFjqPlot::getNumbersTicks( $minValue, $maxValue );
		$data['total'] = array_sum( array_map( "array_sum", $numerics ) );

		return $data;
	}

	/**
	 * Prepare data for the output
	 *
	 * @since 1.8
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	protected function getFormatOutput( array $data ) {

		$this->isHTML = true;

		static $statNr = 0;
		$chartID = 'jqplot-series-' . ++$statNr;

		// RL module
		switch ( $this->params['charttype'] ) {
			case 'bubble':
				SMWOutputs::requireResource( 'ext.srf.jqplot.bubble' );
				break;
			case 'donut':
				SMWOutputs::requireResource( 'ext.srf.jqplot.donut' );
				break;
			case 'scatter':
				SMWOutputs::requireResource( 'ext.srf.jqplot.scatter' );
			case 'line':
			case 'bar':
				if ( in_array( $this->params['datalabels'], array( 'label', 'value', 'percent' ) ) ||
					$this->params['highlighter'] ) {
					SMWOutputs::requireResource( 'ext.srf.jqplot.bar.extended' );
				}else{
					SMWOutputs::requireResource( 'ext.srf.jqplot.bar' );
				};
				break;
		}

		// Encoding
		$requireHeadItem = array ( $chartID => FormatJson::encode( $data )  );
		SMWOutputs::requireHeadItem( $chartID, Skin::makeVariablesScript( $requireHeadItem ) );

		// Processing placeholder
		$processing = SRFUtils::htmlProcessingElement( $this->isHTML );

		// Conversion due to a string as value that can contain %
		$width = strstr( $this->params['width'] ,"%") ? $this->params['width'] : $this->params['width'] . 'px';

		// Chart/graph placeholder
		$chart = Html::rawElement( 'div', array(
			'id'    => $chartID,
			'class' => 'container',
			'style' => "display:none; width: {$width}; height: {$this->params['height']}px;"
			), null
		);

		// Beautify class selector
		$class = $this->params['charttype'] ?  '-' . $this->params['charttype'] : '';
		$class = $this->params['class'] ? $class . ' ' . $this->params['class'] : $class . ' jqplot-common';

		// Chart/graph wrappper
		return Html::rawElement( 'div', array(
			'class' => 'srf-jqplot' . $class,
			), $processing . $chart
		);
	}

	/**
	 * @see SMWResultPrinter::getParamDefinitions
	 *
	 * @since 1.8
	 *
	 * @param $definitions array of IParamDefinition
	 *
	 * @return array of IParamDefinition|array
	 */
	public function getParamDefinitions( array $definitions ) {
		$params = array_merge( parent::getParamDefinitions( $definitions ), SRFjqPlot::getCommonParams() );

		$params['stackseries'] = array(
			'type' => 'boolean',
			'message' => 'srf-paramdesc-stackseries',
			'default' => false,
		);

		$params['group'] = array(
			'message' => 'srf-paramdesc-group',
			'default' => 'subject',
			'values' => array( 'property' , 'subject' ),
		);

		$params['grouplabel'] = array(
			'message' => 'srf-paramdesc-grouplabel',
			'default' => 'subject',
			'values' => array( 'property' , 'subject' ),
		);

		$params['charttype'] = array(
			'message' => 'srf-paramdesc-charttype',
			'default' => 'bar',
			'values' => array( 'bar', 'line', 'donut', 'bubble', 'scatter' ),
		);

		$params['trendline'] = array(
			'message' => 'srf-paramdesc-trendline',
			'default' => 'none',
			'values' => array( 'none', 'exp', 'linear' ),
		);

		return $params;
	}	
}