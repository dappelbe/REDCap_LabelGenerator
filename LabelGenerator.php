<?php
namespace OCTRU\LabelGenerator;

namespace OCTRU\LabelGenerator;

use ExternalModules\AbstractExternalModule;

require_once('LabelGeneratorFunctions.php');

class LabelGenerator extends AbstractExternalModule {

    protected $LblGenFunc = null;

    private $_studyPrefix = "";
    private $_siteMap = array();
    private $_sequentialIn = "0";
    private $_numberOfZeros = 0;
    private $_fld2Count = "";
    private $_fld2CountAtVisit = "";
    private $_siteField = "";
    private $_siteFieldAtVisit = "";
    private $_seperator = "";

    /***
     * Run at the top of every page
     * @param $project_id
     */
    function redcap_every_page_top($project_id) {

        if (!$project_id) {
            return;
        }
        //-- This bit borrowed from the module auto_populate fields,
        //
        if ( (PAGE == 'DataEntry/index.php' && !empty($_GET['id']) )
            || (PAGE == 'surveys/index.php' && !empty($_GET['id']) )
        ) {
            $settings = array();
            $settings['runOnForm']  = $this->getProjectSetting("run-on-form", $project_id);
            $settings['runOnVisit'] = $this->getProjectSetting("run-on-visit", $project_id);
            $settings['runsOn']     = $this->getProjectSetting("label-type", $project_id);
            $idx = 0;
            $record = $_GET['id'];
            $event_id = $_GET['event_id'];
            $instrument = $_GET['page'];
            $this->LblGenFunc = new LabelGeneratorFunctions();
            if ( $this->LblGenFunc->CanProcess( $instrument, $event_id, LabelGeneratorFunctions::$RUNONLOAD,
                $settings, $idx) ) {
                $generatedLabel = $this->ProcessMe($project_id, $record, $idx);
                if ( !is_null($generatedLabel)) {
                        $recs2Save = array();
                        $recs2Save[$this->_fld2CountAtVisit][$this->_fld2Count] = $generatedLabel;
                        \Records::saveData($project_id, 'array', [$record => $recs2Save], 'overwrite', null, null, null, null,
                            null, null, null, [$record => 'Generated Label through the LabelGenerator Module.']);

                        global $Proj;
                        $aux_metadata = $Proj->metadata;
                        $aux_metadata[$this->_fld2Count]['misc'] = "@DEFAULT='" . $generatedLabel . "'" . PHP_EOL .
                            $aux_metadata[$this->_fld2Count]['misc'];
                        $Proj->metadata = $aux_metadata;
                }
            }
        }

    }

    /***
     * Run of form save
     * @param $project_id
     * @param null $record
     * @param $instrument
     * @param $event_id
     * @param null $group_id
     * @param null $survey_hash
     * @param null $response_id
     * @param int $repeat_instance
     * @throws \Exception
     */
	public function redcap_save_record( $project_id,
                                        $record = NULL,
                                        $instrument,
                                        $event_id,
                                        $group_id = NULL,
                                        $survey_hash = NULL,
                                        $response_id = NULL,
                                        $repeat_instance = 1 ) {

        $settings = array();
        $settings['runOnForm']  = $this->getProjectSetting("run-on-form", $project_id);
        $settings['runOnVisit'] = $this->getProjectSetting("run-on-visit", $project_id);
        $settings['runsOn']     = $this->getProjectSetting("label-type", $project_id);
        $idx = 0;
        $this->LblGenFunc = new LabelGeneratorFunctions();
        if ( $this->LblGenFunc->CanProcess( $instrument, $event_id, LabelGeneratorFunctions::$RUNONSAVE, $settings, $idx) ) {
            $generatedLabel = $this->ProcessMe($project_id, $record, $idx);
            if ( !is_null($generatedLabel)) {
                $recs2Save = array();
                $recs2Save[$this->_fld2CountAtVisit][$this->_fld2Count] = $generatedLabel;
                \Records::saveData($project_id, 'array', [$record => $recs2Save],'overwrite', null, null, null, null,
                    null, null, null, [$record => 'Generated Label through the LabelGenerator Module.']);
            }
        }
    }

    public function ProcessMe( $project_id, $record, $idx ) {
        $this->ExtractProjectSettings($project_id, $idx);
        $data = \REDCap::getData($project_id, 'array', $record);
        $data = $data[$record];
        $ctrData = array();
        $siteKey = PHP_INT_MIN;
        $siteLbl = "";
        $filter = "";
        $ctr = 1;

        //-- Check to see that we have not already generated a label for this record.
        if (
            array_key_exists($this->_fld2CountAtVisit, $data)
            && array_key_exists($this->_fld2Count, $data[$this->_fld2CountAtVisit])
            && strlen(trim($data[$this->_fld2CountAtVisit][$this->_fld2Count])) > 0
            ) {
            return null;
        }

        if ( !is_null($this->_siteField) ) {
            if (
                array_key_exists($this->_siteFieldAtVisit, $data)
                && array_key_exists($this->_siteField, $data[$this->_siteFieldAtVisit])) {
                $siteKey = trim(strval($data[$this->_siteFieldAtVisit][$this->_siteField]));
            }
            if ( array_key_exists($siteKey, $this->_siteMap) ) {
                $siteLbl = $this->_siteMap[$siteKey];
            }
        }

        $ctrData = \REDCap::getData($project_id, 'array', null, array($this->_fld2Count,$this->_siteField ));
        foreach ( $ctrData as $k => $v ) {
            if ( $v['repeat_instances'] != null) {
                foreach ( $v['repeat_instances'] as $k2 => $v2 ) {
                    if ( $k2 == $this->_fld2CountAtVisit ) {
                        foreach ( $v2 as $k3 => $v3 ) {
                            foreach ($v3 as $k4 => $v4) {
                                if (array_key_exists($this->_fld2Count, $v4)
                                    && strlen(trim($v4[$this->_fld2Count])) > 0) {
                                    if ($this->_sequentialIn == LabelGeneratorFunctions::$SEQINSITE) {
                                        if (
                                            array_key_exists($this->_siteFieldAtVisit, $v)
                                            && array_key_exists($this->_siteField, $v[$this->_siteFieldAtVisit])
                                            && $v[$this->_siteFieldAtVisit][$this->_siteField] == $siteKey
                                        ) {
                                            $ctr++;
                                        }
                                    } else {
                                        $ctr++;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                if (
                    array_key_exists($this->_fld2CountAtVisit, $v)
                    && array_key_exists($this->_fld2Count, $v[$this->_fld2CountAtVisit])
                    && strlen(trim($v[$this->_fld2CountAtVisit][$this->_fld2Count])) > 0
                ) {
                    if ( $this->_sequentialIn == LabelGeneratorFunctions::$SEQINSITE ) {
                        if (
                            array_key_exists($this->_siteFieldAtVisit, $v)
                            && array_key_exists($this->_siteField, $v[$this->_siteFieldAtVisit])
                            && $v[$this->_siteFieldAtVisit][$this->_siteField] == $siteKey
                        ) {
                            $ctr++;
                        }
                    } else {
                        $ctr++;
                    }
                }
            }
        }

        return $this->LblGenFunc->FormatLabel($this->_studyPrefix, $this->_seperator, $siteLbl,
            $this->_numberOfZeros, $ctr);
    }

    /***
     * Function to get all of the project specific settings that we will need.
     * @param $project_id -> project identifier.
     * @param $idx        -> Settings index.
     */
    private function ExtractProjectSettings($project_id, $idx ) {
        $this->_studyPrefix = $this->getProjectSetting("study-prefix", $project_id)[$idx];
        $tmp = explode(PHP_EOL, $this->getProjectSetting("site-identifier", $project_id)[0]);
        foreach( $tmp as $k => $v ) {
            $tmp2 = explode(',', $v);
            $this->_siteMap[trim(strval($tmp2[0]))] = trim($tmp2[1]);
        }
        $this->_sequentialIn = $this->getProjectSetting("ctr-type", $project_id)[$idx];
        $this->_numberOfZeros = intval($this->getProjectSetting("ctr-length", $project_id)[$idx]);
        $this->_fld2Count = $this->getProjectSetting("ctr-field", $project_id)[$idx];
        $this->_fld2CountAtVisit = $this->getProjectSetting("ctr-field-visit", $project_id)[$idx];
        $this->_siteField = $this->getProjectSetting("site-field-from", $project_id)[$idx];
        $this->_siteFieldAtVisit = $this->getProjectSetting("site-field-from-visit", $project_id)[$idx];
        $this->_seperator = $this->getProjectSetting("number-seperator", $project_id)[$idx];
    }

    /**
     * Checks if the current form has data.
     *
     * @return bool
     *   TRUE if the current form contains data, FALSE otherwise.
     */
    function currentFormHasData() {
        global $double_data_entry, $user_rights, $quesion_by_section, $pageFields;

        $record = $_GET['id'];
        if ($double_data_entry && $user_rights['double_data'] != 0) {
            $record = $record . '--' . $user_rights['double_data'];
        }

        if (PAGE != 'DataEntry/index.php' && $question_by_section && Records::fieldsHaveData($record, $pageFields[$_GET['__page__']], $_GET['event_id'])) {
            // The survey has data.
            return true;
        }

        if (Records::formHasData($record, $_GET['page'], $_GET['event_id'], $_GET['instance'])) {
            // The data entry has data.
            return true;
        }
        return false;
    }

}