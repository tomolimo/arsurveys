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

define ("PLUGIN_ARSURVEYS_VERSION", "3.0.2");

// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// ----------------------------------------------------------------------

/**
 * Init the hooks of the plugin
 * @return null
 */
function plugin_init_arsurveys() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['arsurveys'] = true;

   Plugin::registerClass('PluginArsurveysTicketSatisfaction', ['notificationtemplates_types'  => true]);

   $conf=new Config;
   if ($conf->canUpdate()) {
      Plugin::registerClass('PluginArsurveysConfig', ['addtabon' => 'Config']);
      $PLUGIN_HOOKS['config_page']['arsurveys'] = 'front/config.form.php';
   }

   Plugin::registerClass('PluginArsurveysNotification', ['addtabon' => 'Notification']);

   $PLUGIN_HOOKS['item_update']['arsurveys'] = [
      'TicketSatisfaction' => ['PluginArsurveysTicketSatisfaction', 'plugin_item_update_arsurveys']
      ];
   $PLUGIN_HOOKS['pre_item_update']['arsurveys'] = [
      'TicketSatisfaction' => ['PluginArsurveysTicketSatisfaction', 'plugin_pre_item_update_arsurveys']
      ];
   $PLUGIN_HOOKS['item_purge']['arsurveys'] = [
      'Notification' => ['PluginArsurveysNotification', 'plugin_item_purge_arsurveys']
      ];
   // Notifications
   $PLUGIN_HOOKS['item_get_events']['arsurveys'] =[
      'PluginArsurveysNotificationTargetTicketSatisfaction' => ['PluginArsurveysNotificationTargetTicketSatisfaction', 'addEvents']
      ];
   $PLUGIN_HOOKS['item_action_targets']['arsurveys'] = [
      'PluginArsurveysNotificationTargetTicketSatisfaction' => ['PluginArsurveysNotificationTargetTicketSatisfaction', 'addActionTargets']
      ];
   $PLUGIN_HOOKS['item_get_datas']['arsurveys'] = [
      'PluginArsurveysNotificationTargetTicketSatisfaction' => ['PluginArsurveysNotificationTargetTicketSatisfaction', 'addDatas']
      ];
}


/**
 * Get the name and the version of the plugin - Needed
 * @return array
 */
function plugin_version_arsurveys() {
    return ['name'           => __('AR Surveys', 'arsurveys'),
            'version'        => PLUGIN_ARSURVEYS_VERSION,
            'author'         => 'Olivier Moron',
            'license'        => 'AGPLv3+',
            'homepage'       => 'https://github.com/tomolimo/arsurveys',
            'requirements'   => ['glpi' => ['min' => '9.5',
                                           'max' => '9.6']]
                                ];
}


/**
 * Optional : check prerequisites before install : may print errors or add to message after redirect
 * @return boolean
 */
function plugin_arsurveys_check_prerequisites() {

   if (version_compare(GLPI_VERSION, '9.5', 'lt') || version_compare(GLPI_VERSION, '9.6', 'ge')) {
      echo "This plugin requires GLPI >= 9.5 and < 9.6";      return false;
   }
   return true;
}


/**
 * Check configuration process for plugin : need to return true if succeeded
 * Can display a message only if failure and $verbose is true
 * @param boolean $verbose for verbose mode
 * @return boolean
 */
function plugin_arsurveys_check_config($verbose = false) {
    return true;
}

