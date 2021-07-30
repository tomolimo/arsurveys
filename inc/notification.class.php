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


if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


class PluginArsurveysNotification extends CommonDBTM {


   /**
    * profiles modification
    * @param mixed $item    the item
    * @param mixed $options array of options
    * @return boolean
    */
   function showForm($item, $options = []) {
      global $DB;

      $target = $this->getFormURL();
      if (isset($options['target'])) {
         $target = $options['target'];
      }

      if (($DB->tableExists('glpi_profilerights') && !Notification::canView()) || (!$DB->tableExists('glpi_profilerights') && !Session::haveRight("notification", "r"))) {
         return false;
      }

      $canedit = ($DB->tableExists('glpi_profilerights') && Notification::canUpdate()) || Session::haveRight("notification", "w");

      $bad_survey = false;
      if ($item->fields['event'] == 'bad_survey') {
         $bad_survey = true;
      }

      $config = PluginArsurveysConfig::getInstance();
      $threshold = ($bad_survey?$config->fields['bad_threshold']:$config->fields['good_threshold']); // by default
      if (!$this->getFromDBByNotification($item->getID())) {
         // must create it with default values
         $this->add( [ 'notifications_id' => $item->getID()]);
      }
      if (isset($this->fields['threshold'])) {
         $threshold = $this->fields['threshold'];
      }

      $force_positive_notif = $config->fields['force_positive_notif'];
      if (isset($this->fields['force_positive_notif'])) {
         $force_positive_notif = $this->fields['force_positive_notif'];
      }

      echo "<form action='".$target."' method='post'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='2'>".__("AR Surveys",'arsurveys')." : ".__("Set threshold",'arsurveys') ."</th></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td >".($bad_survey?__("Negative Threshold: if satisfaction survey result is less than or equal (<=) to this value a notification will be triggered as a 'negative survey result'", 'arsurveys'):__("Positive Threshold: if satisfaction survey result is greater than or equal (=>) to this value a notification will be triggered as a 'positive survey result'", 'arsurveys'))."&nbsp;:</td><td >";
      echo "<input type='text' name='threshold' value='".$threshold."'>";
      echo "</td></tr>";

      if (!$bad_survey) {
         // then show the setting to force positive notifications even if user's comment to satisfaction survey is empty
         echo "<tr class='tab_bg_2'>";
         echo "<td >".__("'Positive Survey Result' notifications are not sent when user's comments to satisfaction survey is empty. Send them anyway?", 'arsurveys')."&nbsp;:</td><td >";
         Dropdown::showYesNo("force_positive_notif", $force_positive_notif);
         echo "</td></tr>";
      }
      if ($canedit) {
         echo "<tr class='tab_bg_1'>";
         echo "<td class='center' colspan='2'>";
         echo "<input type='hidden' name='id' value=".$this->getID().">";
         echo "<input type='hidden' name='notifications_id' value=".$item->getID().">";
         echo "<input type='submit' name='update_notification_config' value=\"".__("Save")."\"
               class='submit'>";
         echo "</td></tr>";
      }
      echo "</table>";
      Html::closeForm();
   }

   /**
    * Summary of getFromDBByNotification
    * @param mixed $notifications_id ID of the notification
    * @return boolean
    */
   function getFromDBByNotification($notifications_id) {
      global $DB;
      $res = $DB->request(
                    $this->getTable(),
                    ['notifications_id' => $notifications_id]
         );
      if($res) {
         if($res->numrows() != 1) {
            return false;
         }
         $this->fields = $res->next();
         if(is_array($this->fields) && count($this->fields)) {
            return true;
         } else {
            return false;
         }
      }
      //$query = "SELECT * FROM `".$this->getTable()."`
      //         WHERE `notifications_id` = '" . $notifications_id . "' ";
      //if ($result = $DB->query($query)) {
      //   if ($DB->numrows($result) != 1) {
      //      return false;
      //   }
      //   $this->fields = $DB->fetch_assoc($result);
      //   if (is_array($this->fields) && count($this->fields)) {
      //      return true;
      //   } else {
      //      return false;
      //   }
      //}
      return false;
   }

   /**
    * Summary of getTabNameForItem
    * @param CommonGLPI $item         the item
    * @param mixed      $withtemplate indicate that's use template
    * @return array|string|translated
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Notification' 
          && $item->fields['itemtype'] == "PluginArsurveysTicketSatisfaction" 
          && $item->getID() > 0) {

         return __("AR Surveys", 'arsurveys');
      }
      return '';
   }

   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $item         the item
    * @param mixed      $tabnum       num of tab
    * @param mixed      $withtemplate incate if it(s withtemplate
    * @return boolean
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType()=='Notification' && $item->fields['itemtype'] == "PluginArsurveysTicketSatisfaction" && $item->getID() > 0) {
         $notif = new self();
         $notif->showForm($item);
      }
      return true;
   }

   /**
    * Summary of plugin_item_purge_arsurveys
    * @param mixed $item the item
    * @return false
    */
   static function plugin_item_purge_arsurveys($item) {
      // just delete the record linked to current TicketValidation item
      $me = new self();
      $me->deleteByCriteria(['notifications_id' => $item->getID()]);
   }



}

