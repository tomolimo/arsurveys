<?php
/*
 * -------------------------------------------------------------------------
ARSurveys plugin
Monitors via notifications the results of surveys
Provides bad result notification as well as good result notifications

Copyright (C) 2016 by Raynet SAS a company of A.Raymond Network.

http://www.araymond.com
-------------------------------------------------------------------------

LICENSE

This file is part of ARSurveys plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with GLPI. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */


// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// ----------------------------------------------------------------------


class PluginArsurveysConfig extends Config {

   static private $_instance = null;

   function getName($with_comment = 0) {

      return __("AR Surveys", "arsurveys");
   }

    /**
     * Singleton for the unique config record
     * @return object
     */
   static function getInstance() {

      if (!isset(self::$_instance)) {
         self::$_instance = new self();
         if (!self::$_instance->getFromDB(1)) {
            self::$_instance->getEmpty();
         }
      }
      return self::$_instance;
   }


   static function showConfigForm($item) {
      global $DB;

      $config = self::getInstance();

      $config->showFormHeader();

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan=2>".__("Negative Threshold: if satisfaction survey result is less than or equal (<=) to this value a notification will be triggered as a 'negative survey result'", 'arsurveys')."&nbsp;:</td><td colspan=2>";
      echo "<input type='text' name='bad_threshold' value='".$config->fields['bad_threshold']."'>";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan=2>".__("Positive Threshold: if satisfaction survey result is greater than or equal (=>) to this value a notification will be triggered as a 'positive survey result'", 'arsurveys')."&nbsp;:</td><td colspan=2>";
      echo "<input type='text' name='good_threshold' value='".$config->fields['good_threshold']."'>";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan=2>".__("'Positive Survey Result' notifications are not sent when user's comments to satisfaction survey is empty. Send them anyway?", 'arsurveys')."&nbsp;:</td><td colspan=2>";
      Dropdown::showYesNo("force_positive_notif", $config->fields["force_positive_notif"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan=2>".__("Comments", 'arsurveys')."&nbsp;:";
      echo "</td><td colspan=2 class='center'>";
      echo "<textarea cols='60' rows='5' name='comment' >".$config->fields['comment']."</textarea>";
      echo "</td></tr>\n";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td colspan=2>".__("Last update", 'arsurveys')."&nbsp;: </td><td colspan=2>";
      //echo Html::convDateTime($config->fields["date_mod"]);
      //echo "</td></tr>\n";

      $config->showFormButtons(['candel'=>false]);

      return false;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Config') {
         return __("AR Surveys", 'arsurveys');
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType()=='Config') {
         self::showConfigForm($item);
      }
      return true;
   }

   function prepareInputForUpdate($input) {
      return $input;
   }

}
