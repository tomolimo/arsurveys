<?php
use ScssPhp\ScssPhp\Formatter\Nested;
/*
 * -------------------------------------------------------------------------
ARSurveys plugin
Monitors via notifications the results of surveys
Provides bad result notification as well as good result notifications

Copyright (C) 2016-2021 by Raynet SAS a company of A.Raymond Network.

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

// Class NotificationTarget
class PluginArsurveysNotificationTargetTicketSatisfaction extends NotificationTargetCommonITILObject {

   //Notification to the group of technician in charge of the item
   const ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP = -10000;
   const ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP = -10001;

   private static $ASSIGN;
   private static $OBSERVER;
   private static $REQUESTER;

   

   /**
    * Summary of __construct
    * @param mixed $entity
    * @param mixed $event
    * @param mixed $object
    * @param mixed $options
    */
   function __construct($entity = '', $event = '', $object = null, $options = []) {

      parent::__construct($entity, $event, null, $options); // passes null to prevent $this->obj and $this->target_object to be assigned with wrong values

      // defines static variables
      if (defined('CommonITILActor::ASSIGN')) {
         self::$ASSIGN = constant('CommonITILActor::ASSIGN');
      } else {
         self::$ASSIGN = constant('CommonITILObject::ASSIGN');
      }
      if (defined('CommonITILActor::OBSERVER')) {
         self::$OBSERVER = constant('CommonITILActor::OBSERVER');
      } else {
         self::$OBSERVER = constant('CommonITILObject::OBSERVER');
      }
      if (defined('CommonITILActor::REQUESTER')) {
         self::$REQUESTER = constant('CommonITILActor::REQUESTER');
      } else {
         self::$REQUESTER = constant('CommonITILObject::REQUESTER');
      }

      // needs to define the $this->obj to point to associated Ticket, and $this->target_object
      if (isset($options['item'])) {
         $ticket = new Ticket;
         $ticket->getFromDB( $options['item']->fields['tickets_id'] );
         $this->obj = $ticket;
         $this->getObjectItem($this->raiseevent);
      }
   }


   /**
    * Function addEvents call form item_get_events hook
    * @param PluginArsurveysNotificationTargetTicketSatisfaction $target
    */
   static function addEvents(PluginArsurveysNotificationTargetTicketSatisfaction $target) {

      $target->events = ['bad_survey'  => __('Negative survey result', 'arsurveys'), 
                         'good_survey' => __('Positive survey result', 'arsurveys')];
   }


   /**
    * Function addActionTargets call form item_action_targets hook
    * @param PluginArsurveysNotificationTargetTicketSatisfaction $target
    */
   static function addActionTargets(PluginArsurveysNotificationTargetTicketSatisfaction $target) {

      $check = $target->checkNotificationTarget( $target->data, $target->options );
      if (($target->data['type'] == PluginArsurveysNotificationTargetTicketSatisfaction::ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP
               || $target->data['type'] == PluginArsurveysNotificationTargetTicketSatisfaction::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP)
             && $check ) {
         $target->target = [];
         $result = $target->checkNotificationThreshold($target->data, $target->options);
         if ($result) {
            switch ($target->data['type']) {
               case self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP :
                  $target->getLinkedUserByID($target->options['arsurvey']['users_id']);
                  break;
               case self::ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP:
                  $target->addForGroup(1, $target->data['items_id']);
                  break;
            }
         }
      }
   }


   public function getTags() {
      //$initial = parent::getTags();
      //$this->obj = new Ticket;
      //parent::getTags();
      $tags = ['ticketsatisfaction.action'          => __('Survey answer type', 'arsurveys'),
               'ticketsatisfaction.user'            => __('User name', 'arsurveys'),
               'ticketsatisfaction.ticket'          => __('Ticket number', 'arsurveys'),
               'ticketsatisfaction.ticketentity'    => __('Ticket entity', 'arsurveys'),
               'ticketsatisfaction.ticketname'      => __('Ticket Title', 'arsurveys'),
               'ticketsatisfaction.requesters'      => __('Ticket Requesters', 'arsurveys'),
               'ticketsatisfaction.url'             => __('Satisfaction URL', 'arsurveys'),
               'ticketsatisfaction.date_begin'      => __('Start date', 'arsurveys'),
               'ticketsatisfaction.date_answer'     => __('Answer date', 'arsurveys'),
               'ticketsatisfaction.satisfaction'    => __('Quality satisfaction', 'arsurveys'),
               'ticketsatisfaction.comment'         => __('Survey comment', 'arsurveys'),
               'ticketsatisfaction.friendliness'    => __('Friendliness satisfaction', 'arsurveys'),
               'ticketsatisfaction.responsetime'    => __('Responsetime satisfaction', 'arsurveys'),
               'ticketsatisfaction.assigntousers'   => __('Assigned To Technicians', 'arsurveys'),
               'ticketsatisfaction.assigntogroups'  => __('Assigned To Groups', 'arsurveys'),
               'ticketsatisfaction.ticketopendate'  => __('Ticket opening date', 'arsurveys'),
               'ticketsatisfaction.ticketsolvedate' => __('Ticket resolution date', 'arsurveys')
               ];
      foreach ($tags as $tag => $label) {
         $this->addTagToList(['tag'    => $tag,
                              'label'  => $label,
                              'value'  => true,
                              'events' => parent::TAG_FOR_ALL_EVENTS]);
      }
   }


   /**
    * Function addDatas call from item_get_datas
    * @param PluginArsurveysNotificationTargetTicketSatisfaction $target
    */
   static function addDatas(PluginArsurveysNotificationTargetTicketSatisfaction $target) {

      global $CFG_GLPI;

      $events = $target->getAllEvents();
      
      $target->data['##ticketsatisfaction.action##'] = $events[$target->raiseevent];

      $locTicket = $target->obj;

      $locTicketSatisfaction = $target->options['ticketsatisfaction']; //$options['item']->fields['ticketsatisfaction'] ;
      $user = new User();
      $user->getFromDB(Session::getLoginUserID());

      $target->data['##ticketsatisfaction.user##'] = $user->getName( );
      $target->data['##ticketsatisfaction.ticket##'] = $locTicket->getID();
      $target->data['##ticketsatisfaction.ticketname##'] = $locTicket->fields['name'];
      $target->data['##ticketsatisfaction.ticketopendate##'] = $locTicket->fields['date'];
      $target->data['##ticketsatisfaction.ticketsolvedate##'] = $locTicket->fields['solvedate'];

      $target->data['##ticketsatisfaction.url##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=ticket_".
                                     $locTicket->getID().'_Ticket$3');
      $target->data['##ticketsatisfaction.date_begin##'] = $locTicketSatisfaction->fields['date_begin'];
      $target->data['##ticketsatisfaction.date_answer##'] = $locTicketSatisfaction->fields['date_answered'];
      $target->data['##ticketsatisfaction.satisfaction##'] = $locTicketSatisfaction->fields['satisfaction'];
      $target->data['##ticketsatisfaction.comment##'] = $locTicketSatisfaction->fields['comment'];

      // if this survey criterion is existing in $options
      if (isset($target->options['ticketsatisfaction']->input['friendliness'])) {
         $target->data['##ticketsatisfaction.friendliness##'] = $target->options['ticketsatisfaction']->input['friendliness'];
      }

      // if this survey criterion is existing in $options
      if (isset($target->options['ticketsatisfaction']->input['responsetime'])) {
         $target->data['##ticketsatisfaction.responsetime##'] = $target->options['ticketsatisfaction']->input['responsetime'];
      }

      $target->data["##ticketsatisfaction.assigntousers##"] = '';
      if ($locTicket->countUsers(self::$ASSIGN)) {
         $users = [];
         foreach ($locTicket->getUsers(self::$ASSIGN) as $tmp) {
            $uid = $tmp['users_id'];
            $user_tmp = new User();
            if ($user_tmp->getFromDB($uid)) {
               $users[$uid] = $user_tmp->getName();
            }
         }
         $target->data["##ticketsatisfaction.assigntousers##"] = implode(', ', $users);
      }

      $target->data["##ticketsatisfaction.assigntogroups##"] = '';
      if ($locTicket->countGroups(self::$ASSIGN)) {
         $groups = [];
         foreach ($locTicket->getGroups(self::$ASSIGN) as $tmp) {
            $gid = $tmp['groups_id'];
            $groups[$gid] = Dropdown::getDropdownName('glpi_groups', $gid);
         }
         $target->data["##ticketsatisfaction.assigntogroups##"] = implode(', ', $groups);
      }

      $entity = new Entity();
      if ($entity->getFromDB($locTicket->getField('entities_id'))) {
         $target->data["##ticketsatisfaction.ticketentity##"] = $entity->getField('completename');
      }

      $target->data["##ticketsatisfaction.requesters##"] = '';
      if ($locTicket->countUsers(self::$REQUESTER)) {
         $users = [];
         foreach ($locTicket->getUsers(self::$REQUESTER) as $tmpusr) {
            $uid = $tmpusr['users_id'];
            $user_tmp = new User();
            if ($uid && $user_tmp->getFromDB($uid)) {
               $users[] = $user_tmp->getName();
            } else {
               // Anonymous users only in xxx.authors, not in authors
               $users[] = $tmpusr['alternative_email'];
            }
         }
         $target->data["##ticketsatisfaction.requesters##"] = implode(', ', $users);
      }
   }


   /**
   * Summary of checkNotificationTarget
   * @param mixed $data
   * @param mixed $options
   */
   function checkNotificationTarget($data, &$options) {
      // get ticket
      $tick = $this->obj;
      $members = [ ];
      $ids = [];
      $ret = false; // no users
      if (!isset($options['arsurvey']['users_id'])) {
         $options['arsurvey']['users_id']=[]; // empty array
      }

      $grp = new Group;
      $grp->getFromDB( $data['items_id'] );
      Group_User::getDataForGroup( $grp, $members, $ids );

      // search for all ticket tech belonging to this group
      // and store them into options
      // will be used later

      foreach ($tick->getUsers( self::$ASSIGN ) as $tech) {
         if (in_array( $tech['users_id'], $ids )) {
            // then send notification
            $options['arsurvey']['users_id'][] = $tech['users_id'];
            $ret = true; // at least one user
         }

      }
      return $ret;
   }


   /**
    * Summary of checkNotificationThreshold
    * check if the notification have to be sent
    * @param mixed $data
    * @param mixed $options
    * @return bool
    */
   function checkNotificationThreshold($data, $options) {
      $config = PluginArsurveysConfig::getInstance();
      $notif = new PluginArsurveysNotification;
      if (!$notif->getFromDBByNotification( $data['notifications_id'] )) {
         $notif->fields['threshold'] = null;
         $notif->fields['force_positive_notif'] = null;
      }
      switch ($this->raiseevent) {
         case 'bad_survey' :
            $threshold = ($notif->fields['threshold']!=null ? $notif->fields['threshold'] : $config->fields['bad_threshold']);
            if ((in_array('satisfaction', $options['item']->updates) || in_array( 'friendliness', $options['item']->updates ) || in_array( 'responsetime', $options['item']->updates ))
               && ($options['item']->input['satisfaction'] <= $threshold
                  || (isset($options['item']->input['friendliness']) && $options['item']->input['friendliness'] <= $threshold)
                  || (isset($options['item']->input['responsetime']) && $options['item']->input['responsetime'] <= $threshold)
                  )
               ) {
               return true;
            }
            break;
         case 'good_survey' :
            $threshold = ($notif->fields['threshold']!=null ? $notif->fields['threshold'] : $config->fields['good_threshold']);
            $force_positive_notif=($notif->fields['force_positive_notif']!=null ? $notif->fields['force_positive_notif'] : $config->fields['force_positive_notif']);
            if ((in_array('satisfaction', $options['item']->updates) ||
               in_array('friendliness', $options['item']->updates) ||
               in_array('responsetime', $options['item']->updates) ||
               in_array('comment', $options['item']->updates)) &&
               ($options['item']->input['satisfaction'] >= $threshold) &&
               (!isset( $options['item']->input['friendliness'] ) || $options['item']->input['friendliness'] >= $threshold) &&
               (!isset( $options['item']->input['responsetime'] ) || $options['item']->input['responsetime'] >= $threshold) &&
               ( $force_positive_notif || !empty($options['item']->input['comment']) )) {

               return true;
            }
            break;
      }
      return false;
   }


   /**
    * Summary of getLinkedUserByID
    * Retreive info for users in $ids of type $type
    * @param mixed $ids array of users_id
    * @param mixed $type
    */
   function getLinkedUserByID($ids, $type = false) {
      global $DB, $CFG_GLPI;
      if (!$type) {
         $type = self::$ASSIGN;
      }
      $dbu = new DbUtils();
      $userlinktable = $dbu->getTableForItemType($this->obj->userlinkclass);
      $fkfield       = $this->obj->getForeignKeyField();

      //Look for the user by his id
      //$query =        $this->getDistinctUserSql().",
      //                `$userlinktable`.`use_notification` AS notif,
      //                `$userlinktable`.`alternative_email` AS altemail
      //         FROM `$userlinktable`
      //         LEFT JOIN `glpi_users` ON (`$userlinktable`.`users_id` = `glpi_users`.`id`)".
      //         ($type!=self::$OBSERVER?$this->getProfileJoinSql():"")."
      //         WHERE `$userlinktable`.`$fkfield` = '".$this->obj->fields["id"]."'
      //               AND `$userlinktable`.`type` = '$type'
      //               AND `$userlinktable`.`users_id` IN (".implode(', ', $ids).")";

      $query2 = $this->getDistinctUserCriteria();
      if($type != self::$OBSERVER) {
         $tab = $this->getProfileJoinCriteria();
         foreach($tab as $key => $crit) {
            $query2[$key] = $crit;
         }
      }
      
      array_push($query2['FIELDS'], $userlinktable.".use_notification AS notif", $userlinktable.".alternative_email AS altemail");
      //$query2['FIELDS']  = [$userlinktable.".use_notification AS notif",$userlinktable.".alternative_email AS altemail"];
      //$query2['FIELDS'][]  = $userlinktable.".alternative_email AS altemail";
      $query2['FROM']      = $userlinktable;
      $query2['LEFT JOIN'] = ['glpi_users' => ['FKEY' => ['glpi_users' => 'id', $userlinktable => 'users_id']]];
      $query2['WHERE']     = ['AND' => [$userlinktable.".".$fkfield => $this->obj->fields['id'], $userlinktable.".type" => $type, $userlinktable.".users_id" => $ids]]; 
      foreach ($DB->request($query2) as $data) {
         //Add the user email and language in the notified users list
         if ($data['notif']) {
            $author_email = UserEmail::getDefaultForUser($data['users_id']);
            $author_lang  = $data["language"];
            $author_id    = $data['users_id'];

            if (!empty($data['altemail'])
                && $data['altemail'] != $author_email
                && NotificationMailing::isUserAddressValid($data['altemail'])) {
               $author_email = $data['altemail'];
            }
            if (empty($author_lang)) {
               $author_lang = $CFG_GLPI["language"];
            }
            if (empty($author_id)) {
               $author_id = -1;
            }

            $user = [
               'language' => $author_lang,
               'users_id' => $author_id,
               'type'     => $type // $type is passed only to authorize view of tickets by watchers (or observers)
            ];
            if ($this->isMailMode()) {
               $user['email'] = $author_email;
            }
            $this->addToRecipientsList($user);
         }
      }
   }


   /**
    * Allows to add more notification targets
    * Can be overridden in some case (for example Ticket)
    *
    * @param string $event specif event to get additional targets (default '')
    *
    * @return void
    */
   function addAdditionalTargets($event = '') {
       global $DB;
       $entity = $this->entity;
       $dbu = new DbUtils();

       $res = $DB->request([
                     'SELECT' => ['id', 'name'],
                     'FROM'   => 'glpi_groups',
                     'WHERE'  => [
                        'AND' => [
                           $dbu->getEntitiesRestrictCriteria('glpi_groups', 'entities_id', $entity, true), 'is_usergroup' => 1, 'is_notify' => 1]],
                     'ORDER'  => 'name'
          ]);
       // Filter groups which can be notified and have members (as notifications are sent to members)
       //$query = "SELECT `id`, `name`
       //         FROM `glpi_groups`".
       //          $dbu->getEntitiesRestrictRequest(" WHERE", 'glpi_groups', 'entities_id', $entity, true)."
       //               AND `is_usergroup`
       //               AND `is_notify`
       //         ORDER BY `name`";

      foreach ($res as $data) {
          //Add group
         $this->addTarget($data["id"], __('Technician in charge of this ticket within Group', 'arsurveys'). " " .$data["name"], self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP);
         $this->addTarget($data["id"], __('Manager of Group for Technician in charge of this ticket within Group', 'arsurveys'). " " .$data["name"], self::ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP);
      }
   }


   /**
    * Display notification targets
    *
    * @param Notification $notification the Notification object
   **/
   function showNotificationTargets(Notification $notification) {
      global $DB;

      if ($notification->getField('itemtype') != '') {
         $notifications_id = $notification->fields['id'];
         $this->getNotificationTargets($_SESSION['glpiactive_entity']);

         $canedit = $notification->can($notifications_id, 'w');

         $options = "";
         $query2 = [
            'SELECT' => ['glpi_notificationtargets.items_id', 'glpi_notificationtargets.id'],
            'FROM'   => 'glpi_notificationtargets',
            'WHERE'  => [
               'AND' => [
                  'glpi_notificationtargets.notifications_id' => $notifications_id, 
                  'glpi_notificationtargets.type' => Notification::USER_TYPE
               ]
            ],
            'ORDER'  => 'glpi_notificationtargets.items_id'
         ];
         // Get User mailing
         //$query = "SELECT `glpi_notificationtargets`.`items_id`,
         //                 `glpi_notificationtargets`.`id`
         //          FROM `glpi_notificationtargets`
         //          WHERE `glpi_notificationtargets`.`notifications_id` = '$notifications_id'
         //                AND `glpi_notificationtargets`.`type` = '" . Notification::USER_TYPE . "'
         //          ORDER BY `glpi_notificationtargets`.`items_id`";

         foreach ($DB->request($query2) as $data) {
            if (isset($this->notification_targets[Notification::USER_TYPE."_".$data["items_id"]])) {
               unset($this->notification_targets[Notification::USER_TYPE."_".$data["items_id"]]);
            }

            if (isset($this->notification_targets_labels[Notification::USER_TYPE]
                                                        [$data["items_id"]])) {
               $name = $this->notification_targets_labels[Notification::USER_TYPE][$data["items_id"]];
            } else {
               $name = "&nbsp;";
            }
            $options .= "<option value='" . $data["id"] . "'>" . $name . "</option>";
         }

         // Get Profile mailing

         $query2['SELECT'][] = 'glpi_profiles.name AS prof';
         $query2['LEFT JOIN'] = 	[
            'glpi_profiles' => [
               'FKEY' => [
                  'glpi_notificationtargets' => 'items_id', 
                  'glpi_profiles' => 'id'
               ]
            ]
         ];
         $query2['WHERE']['AND']['glpi_notificationtargets.type'] = Notification::PROFILE_TYPE;
         $query2['ORDER'] = 'prof';

         $query = "SELECT `glpi_notificationtargets`.`items_id`,
                          `glpi_notificationtargets`.`id`,
                          `glpi_profiles`.`name` AS `prof`
                   FROM `glpi_notificationtargets`
                   LEFT JOIN `glpi_profiles`
                        ON (`glpi_notificationtargets`.`items_id` = `glpi_profiles`.`id`)
                   WHERE `glpi_notificationtargets`.`notifications_id` = '$notifications_id'
                         AND `glpi_notificationtargets`.`type` = '" . Notification::PROFILE_TYPE . "'
                   ORDER BY `prof`";

         foreach ($DB->request($query2) as $data) {
            $options .= "<option value='" . $data["id"] . "'>" . __("Profile") . " " .
                        $data["prof"] . "</option>";

            if (isset($this->notification_targets[Notification::PROFILE_TYPE."_".$data["items_id"]])) {
               unset($this->notification_targets[Notification::PROFILE_TYPE."_".$data["items_id"]]);
            }
         }

         $query2['SELECT'][2] = 'glpi_groups.name AS name';
         unset($query['LEFT JOIN']);
         $query2['LEFT JOIN'] = 	[
            'glpi_groups' => [
               'FKEY' => [
                  'glpi_notificationtargets' => 'items_id', 
                  'glpi_groups' => 'id'
               ]
            ]
         ];
         $query2['WHERE']['AND']['glpi_notificationtargets.type'] = Notification::GROUP_TYPE;
         $query2['ORDER'] = 'name';

         // Get Group mailing
         //$query = "SELECT `glpi_notificationtargets`.`items_id`,
         //                 `glpi_notificationtargets`.`id`,
         //                 `glpi_groups`.`name` AS `name`
         //          FROM `glpi_notificationtargets`
         //          LEFT JOIN `glpi_groups`
         //               ON (`glpi_notificationtargets`.`items_id` = `glpi_groups`.`id`)
         //          WHERE `glpi_notificationtargets`.`notifications_id`='$notifications_id'
         //                AND `glpi_notificationtargets`.`type` = '" . Notification::GROUP_TYPE . "'
         //          ORDER BY `name`;";

         foreach ($DB->request($query2) as $data) {
            $options .= "<option value='" . $data["id"] . "'>" . __('Group'). " " .
                        $data["name"] . "</option>";

            if (isset($this->notification_targets[Notification::GROUP_TYPE."_".$data["items_id"]])) {
               unset($this->notification_targets[Notification::GROUP_TYPE."_".$data["items_id"]]);
            }
         }

         $query2['WHERE']['AND']['glpi_notificationtargets.type'] = Notification::SUPERVISOR_GROUP_TYPE;
         
         // Get Group mailing
         //$query = "SELECT `glpi_notificationtargets`.`items_id`,
         //                 `glpi_notificationtargets`.`id`,
         //                 `glpi_groups`.`name` AS `name`
         //          FROM `glpi_notificationtargets`
         //          LEFT JOIN `glpi_groups`
         //               ON (`glpi_notificationtargets`.`items_id` = `glpi_groups`.`id`)
         //          WHERE `glpi_notificationtargets`.`notifications_id`='$notifications_id'
         //                AND `glpi_notificationtargets`.`type`
         //                                                = '".Notification::SUPERVISOR_GROUP_TYPE."'
         //          ORDER BY `name`;";

         foreach ($DB->request($query2) as $data) {
            $options .= "<option value='" . $data["id"] . "'>" . __("Manager").' '.
                        __("Group") . " " .$data["name"] . "</option>";

            if (isset($this->notification_targets[Notification::SUPERVISOR_GROUP_TYPE."_".
                                                  $data["items_id"]])) {

               unset($this->notification_targets[Notification::SUPERVISOR_GROUP_TYPE."_".
               $data["items_id"]]);
            }
         }

         $query2['WHERE']['AND']['glpi_notificationtargets.type']= self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP;
         // Get Special ARSurvey Group mailing
         //$query = "SELECT `glpi_notificationtargets`.`items_id`,
         //                 `glpi_notificationtargets`.`id`,
         //                 `glpi_groups`.`name` AS `name`
         //          FROM `glpi_notificationtargets`
         //          LEFT JOIN `glpi_groups`
         //               ON (`glpi_notificationtargets`.`items_id` = `glpi_groups`.`id`)
         //          WHERE `glpi_notificationtargets`.`notifications_id`='$notifications_id'
         //                AND `glpi_notificationtargets`.`type`
         //                                                = '".self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP."'
         //          ORDER BY `name`;";

         foreach ($DB->request($query2) as $data) {
            $options .= "<option value='" . $data["id"] . "'>" . __('Technician in charge of this ticket within Group', 'arsurveys') . " " .$data["name"] . "</option>";

            if (isset($this->notification_targets[self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP."_".
                                                  $data["items_id"]])) {

               unset($this->notification_targets[self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP."_".
               $data["items_id"]]);
            }
         }


         $query2['WHERE']['AND']['glpi_notificationtargets.type'] = self::ARSURVEY_ITEM_TECH_IN_CHARGE_IN_GROUP;
         // Get Special ARSurvey Group mailing for group managers
         //$query = "SELECT `glpi_notificationtargets`.`items_id`,
         //                 `glpi_notificationtargets`.`id`,
         //                 `glpi_groups`.`name` AS `name`
         //          FROM `glpi_notificationtargets`
         //          LEFT JOIN `glpi_groups`
         //               ON (`glpi_notificationtargets`.`items_id` = `glpi_groups`.`id`)
         //          WHERE `glpi_notificationtargets`.`notifications_id`='$notifications_id'
         //                AND `glpi_notificationtargets`.`type`
         //                                                = '".self::ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP."'
         //          ORDER BY `name`;";

         foreach ($DB->request($query2) as $data) {
            $options .= "<option value='" . $data["id"] . "'>" . __('Manager of Group for Technician in charge of this ticket within Group', 'arsurveys'). " " .$data["name"] . "</option>";

            if (isset($this->notification_targets[self::ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP."_".
                                                  $data["items_id"]])) {

               unset($this->notification_targets[self::ARSURVEY_MANAGER_TECH_IN_CHARGE_IN_GROUP."_".
               $data["items_id"]]);
            }
         }

         if ($canedit) {
            echo "<td class='right'>";

            if (count($this->notification_targets)) {
               echo "<select name='mailing_to_add[]' multiple size='5'>";

               foreach ($this->notification_targets as $key => $val) {
                  list ($type, $items_id) = explode("_", $key);
                  echo "<option value='$key'>".$this->notification_targets_labels[$type][$items_id].
                  "</option>";
               }

               echo "</select>";
            }

            echo "</td><td class='center'>";

            if (count($this->notification_targets)) {
               echo "<input type='submit' class='submit' name='mailing_add' value='".
               __("Add")." >>'>";
            }
            echo "<br><br>";

            if (!empty($options)) {
               echo "<input type='submit' class='submit' name='mailing_delete' value='<< ".
               __("Delete")."'>";
            }
            echo "</td><td>";

         } else {
            echo "<td class='center'>";
         }

         if (!empty($options)) {
            echo "<select name='mailing_to_delete[]' multiple size='5'>";
            echo $options ."</select>";
         } else {
            echo "&nbsp;";
         }
         echo "</td>";
      }
   }
}
