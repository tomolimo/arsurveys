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

/**
 * Install process for plugin : need to return true if succeeded
 * @return boolean
 */
function plugin_arsurveys_install() {
   global $DB;


      if (!$DB->tableExists('glpi_plugin_arsurveys_configs')) {
         $query = "CREATE TABLE `glpi_plugin_arsurveys_configs` (
	         `id` INT(11) NOT NULL AUTO_INCREMENT,
	         `bad_threshold` INT(11) NOT NULL ,
	         `good_threshold` INT(11) NOT NULL ,
	         `force_positive_notif` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'to send positive notification even if user comment is empty',
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
	         `comment` TEXT NULL,
	         PRIMARY KEY (`id`)
         )
         ENGINE=InnoDB
         ;

         ";

         $DB->query( $query ) or die("Can't create 'glpi_plugin_arsurveys_configs' table");

         // insert default configuration
         $DB->insertOrDie(
                  'glpi_plugin_arsurveys_configs',
                  [
                     'bad_threshold' => 2,
                     'good_threshold'=> 4,
                     'date_mod'      => new \QueryExpression('NOW()'),
                     'comment'       => 'These are by default for all ARSurvey notifications, but may be redefined on a per Notification basis.'
                  ],
                  "Can't insert configuration in 'glpi_plugin_arsurveys_configs' table"
         );

         // notification template
         $res = $DB->request('glpi_notificationtemplates', ['itemtype' => 'PluginArsurveysTicketSatisfaction']);
         
         if ($res) {
            if ($res->numrows() == 0) {
               // insert default notification template
               $DB->insertOrDie(
                        'glpi_notificationtemplates', 
                        [
                           'name'      => 'Ticket Survey Monitor', 
                           'itemtype'   => 'PluginArsurveysTicketSatisfaction', 
                           'date_mod'  => new \QueryExpression('NOW()'),
                           'date_creation'     => new \QueryExpression('NOW()')
                        ], 
                        "Can't insert 'Ticket Survey Monitor' notification template in 'glpi_notificationtemplates' table"
               );

               $notiftemplateid = $DB->insert_id();

               // insert default notififaction template translation
               $content_text =  '##lang.ticketsatisfaction.action##: ##ticketsatisfaction.action##

                     ##lang.ticketsatisfaction.user##: ##ticketsatisfaction.user##

                     ##lang.ticketsatisfaction.ticketentity##: ##ticketsatisfaction.ticketentity##

                     ##lang.ticketsatisfaction.ticket##: ##ticketsatisfaction.ticket##

                     ##lang.ticketsatisfaction.requesters##: ##ticketsatisfaction.requesters##

                     ##lang.ticketsatisfaction.ticketname##: ##ticketsatisfaction.ticketname##

                     ##lang.ticketsatisfaction.url##: ##ticketsatisfaction.url##

                     ##lang.ticketsatisfaction.date_begin##: ##ticketsatisfaction.date_begin##

                     ##lang.ticketsatisfaction.date_answer##: ##ticketsatisfaction.date_answer##

                     ##lang.ticketsatisfaction.satisfaction##: ##ticketsatisfaction.satisfaction##

                     ##lang.ticketsatisfaction.comment##: ##ticketsatisfaction.comment##

                     ##lang.ticketsatisfaction.assigntousers##: ##ticketsatisfaction.assigntousers##

                     ##lang.ticketsatisfaction.assigntogroups##: ##ticketsatisfaction.assigntogroups##

                     ##lang.ticketsatisfaction.ticketopendate##: ##ticketsatisfaction.ticketopendate##

                     ##lang.ticketsatisfaction.ticketsolvedate##: ##ticketsatisfaction.ticketsolvedate##';
               $content_html = '&lt;table&gt;
                     &lt;tbody&gt;
                     &lt;tr&gt;&lt;th colspan=\"2\"&gt;##lang.ticketsatisfaction.action##: ##ticketsatisfaction.action##&lt;/th&gt;&lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.user##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.user##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.ticket##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.ticket##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.ticketentity##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.ticketentity##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.requesters##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.requesters##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.ticketname##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.ticketname##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.url##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.url##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.date_begin##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.date_begin##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.date_answer##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.date_answer##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.satisfaction##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.satisfaction##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.comment##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.comment##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.assigntousers##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.assigntousers##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.assigntogroups##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.assigntogroups##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.ticketopendate##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.ticketopendate##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;tr&gt;
                     &lt;td&gt;##lang.ticketsatisfaction.ticketsolvedate##&lt;/td&gt;
                     &lt;td&gt;##ticketsatisfaction.ticketsolvedate##&lt;/td&gt;
                     &lt;/tr&gt;
                     &lt;/tbody&gt;
                     &lt;/table&gt;';

               $DB->insertOrDie(
                        'glpi_notificationtemplatetranslations',
                        [
                           'notificationtemplates_id' => $notiftemplateid,
                           'subject'      => 'Ticket ###ticketsatisfaction.ticket## - ##ticketsatisfaction.action##',
                           'content_text' => $content_text,
                           'content_html' => $content_html
                        ],
                        "Add notification template translation in 'glpi_notificationtemplatetranslations' table"
               );
               $DB->insertOrDie(
                        'glpi_notifications',
                        [
                           'name'         => 'Negative Survey Results',
                           'entities_id'  => 0,
                           'itemtype'     => 'PluginArsurveysTicketSatisfaction',
                           'event'        => 'bad_survey',
                           'is_recursive' => 1,
                           'is_active'    => 1,
                           'date_mod'     => new \QueryExpression('NOW()'),
                           'date_creation'=> new \QueryExpression('NOW()')
                        ],
                        "Add Negative Survey Result notification in 'glpi_notifications' table"
                  );

               $notifid = $DB->insert_id();
               $DB->insertOrDie(
                        'glpi_notificationtargets',
                        [
                           'items_id'  => 10,
                           'type'      => 1,
                           'notifications_id' => $notifid
                        ],
                        "Add Negative Survey Result notification target in 'glpi_notificationtargets' table"
               );

               $DB->insertOrDie(
                        'glpi_notifications',
                        [
                           'name'         => 'Positive Survey Results',
                           'entities_id'  => 0,
                           'itemtype'     => 'PluginArsurveysTicketSatisfaction',
                           'event'        => 'good_survey',
                           'is_recursive' => 1,
                           'is_active'    => 1,
                           'date_mod'     => new \QueryExpression('NOW()'),
                           'date_creation'=> new \QueryExpression('NOW()')
                        ],
                        "Add Positive Survey Result notification in 'glpi_notifications' table"
               );

               $notifid = $DB->insert_id();

               $DB->insertOrDie(
                        'glpi_notificationtargets',
                        [
                           'items_id'  => 10,
                           'type'      => 1,
                           'notifications_id' => $notifid
                        ],
                        "Add Positive Survey Result notification target in 'glpi_notificationtargets' table"
               );

            }
         }

      } else {
         // table is already existing
         // must test for missing fields
         if (!$DB->fieldExists('glpi_plugin_arsurveys_configs', 'force_positive_notif')) {
            $query = "ALTER TABLE `glpi_plugin_arsurveys_configs`
                  	ADD COLUMN `force_positive_notif` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'to force positive notification even if user comment is empty'
                     AFTER `good_threshold`;";
            $DB->query( $query ) or die("Add 'force_positive_notif' field in 'glpi_plugin_arsurveys_configs' table");

         }
      }

      if (!$DB->tableExists('glpi_plugin_arsurveys_notifications')) {
         $query = "CREATE TABLE `glpi_plugin_arsurveys_notifications` (
	         `id` INT(11) NOT NULL AUTO_INCREMENT,
	         `notifications_id` INT(11) NOT NULL,
	         `threshold` INT(11) NULL DEFAULT NULL,
	         `force_positive_notif` TINYINT(1) NULL DEFAULT NULL COMMENT 'to send positive notification even if user comment is empty',
            PRIMARY KEY (`id`),
	         UNIQUE INDEX `notifications_id` (`notifications_id`)
         )
         ENGINE=InnoDB
         ;

         ";
         $DB->query( $query ) or die("Can't insert configuration in 'glpi_plugin_arsurveys_notifications' table");

      } else {
         // table is already existing
         // must test for missing fields
         if (!$DB->fieldExists('glpi_plugin_arsurveys_notifications', 'force_positive_notif')) {
            $query = "ALTER TABLE `glpi_plugin_arsurveys_notifications`
	                  ADD COLUMN `force_positive_notif` TINYINT(1) NULL DEFAULT NULL COMMENT 'to send positive notification even if user comment is empty'
                     AFTER `threshold`;
                  ";
            $DB->query( $query ) or die("Add 'force_positive_notif' field in 'glpi_plugin_arsurveys_notifications' table");
         }
      }
   
   return true;
}

/**
 * Uninstall process for plugin : need to return true if succeeded
 * @return boolean
 */
function plugin_arsurveys_uninstall() {

    return true;
}


