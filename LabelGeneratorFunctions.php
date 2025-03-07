<?php

namespace OCTRU\LabelGenerator;

class LabelGeneratorFunctions
{
    public static $RUNONSAVE = "1";
    public static $RUNONLOAD = "2";

    public static $SEQINSITE  = "1";
    public static $SEQINSTUDY = "2";

    /***
     * Function to determine if the Label Generator function can run.
     * @param $instrument -> The Instrument being saved
     * @param $event      -> The Event at which the Instrument being saved is.
     * @param $runOn      -> Which functions are we calling from
     * @param $settings   -> Parameters that we need to test against
     * @param $idx        -> The index in the settings array
     * @return bool       -> true if all good, else false
     */
    public function CanProcess($instrument, $event, $runOn, $settings, &$idx) : bool {

        if ( is_null($instrument)
            || is_null($event)
            || !( $runOn == LabelGeneratorFunctions::$RUNONSAVE || $runOn == LabelGeneratorFunctions::$RUNONLOAD )
            || is_null( $settings )
            || !array_key_exists( 'runOnForm', $settings)
            || !array_key_exists( 'runOnVisit', $settings)
            || !array_key_exists( 'runsOn', $settings)
        ) {
            return false;
        }


        $runOnForm = $settings['runOnForm'];
        $runOnVisit = $settings['runOnVisit'];
        $runsOn = $settings['runsOn'];

        $settingGroup = 0;
        foreach ($runOnForm as $form) {
            if ($form == $instrument) {
                if (count($runOnVisit) == count($runOnForm)
                    && count($runsOn) == count($runOnForm)) {
                    if ($runOnVisit[$settingGroup] == $event
                        && $runsOn[$settingGroup] == $runOn) {
                        $idx = $settingGroup;
                        return true;
                    }
                }
            }
            $settingGroup++;
        }

        return false;
    }

    /***
     * Function to format the label as we expect it, i.e Study-Site-PaddedCtr
     * @param $studyPrefix    -> The Prefix to use, can be empty
     * @param $seperator      -> what we use to separate the components, e.g "-"
     * @param $siteLabel      -> The text to use as a site label
     * @param $numberOfZeros  -> how much padding do we need
     * @param $ctr            -> The number to append
     * @return string         -> a label, e.g. FA-JRH-00001
     */
    public function FormatLabel( $studyPrefix, $seperator, $siteLabel, $numberOfZeros, $ctr) : string {
        $retVal = "";
        $sep = "";

        if ( !is_null($seperator) ) {
            $sep = trim($seperator);
        }

        if ( !is_null($studyPrefix) && strlen(trim($studyPrefix)) > 0 ) {
            $retVal .= trim($studyPrefix) . $sep;
        }

        if ( !is_null($siteLabel) && strlen(trim($siteLabel)) > 0 ) {
            $retVal .= trim($siteLabel) . $sep;
        }

        if ( is_null($ctr) || strlen(trim($ctr)) == 0) {
            $retVal .= "NaN";
        } else {
            if ( is_null($numberOfZeros) || !is_integer( $numberOfZeros) ) {
                $retVal .= $ctr;
            } else {
                $retVal .= str_pad(trim($ctr), $numberOfZeros, "0", STR_PAD_LEFT);
            }
        }

        return $retVal;
    }

}