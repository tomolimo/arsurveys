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

// Class NotificationTarget
class PluginArsurveysNotificationTargetTicketSatisfaction extends NotificationTargetCommonITILObject {

    private $tags = array('ticketsatisfaction.action'       => 'Survey answer type',
                      'ticketsatisfaction.user'             => 'User name',
                      'ticketsatisfaction.ticket'           => 'Ticket number',
                      'ticketsatisfaction.ticketname'       => 'Ticket Title',
                      'ticketsatisfaction.url'              => 'Satisfaction URL',
                      'ticketsatisfaction.date_begin'       => 'Start date',
                      'ticketsatisfaction.date_answer'      => 'Answer date',
                      'ticketsatisfaction.satisfaction'     => 'Quality satisfaction',
                      'ticketsatisfaction.comment'          => 'Survey comment',
                      'ticketsatisfaction.friendliness'     => 'Friendliness satisfaction',
                      'ticketsatisfaction.responsetime'     => 'Responsetime satisfaction'
                      );

    
    function __construct($entity='', $event='', $object=null, $options=array()){

       parent::__construct($entity, $event, null, $options); // passes null to prevent $this->obj and $this->target_object to be assigned with wrong values

      // needs to define the $this->obj to point to associated Ticket, and $this->target_object
       $ticket = new Ticket ;
       $ticket->getFromDB( $options['item']->fields['tickets_id'] ) ;
       $this->obj = $ticket;
       $this->getObjectItem($this->raiseevent);
    }

    function getEvents() {
        global $LANG ;
        return array('bad_survey' => $LANG['plugin_arsurveys']['bad_survey'], 
                     'good_survey' => $LANG['plugin_arsurveys']['good_survey']);
    }

    /**
     * Get all data needed for template processing
     **/
    function getDatasForTemplate($event, $options=array()) {
        global $CFG_GLPI;

        $events = $this->getAllEvents();

        $this->datas['##ticketsatisfaction.action##'] = $events[$event];

        $locTicket = $this->obj ;

        $locTicketSatisfaction = $options['ticketsatisfaction']; //$options['item']->fields['ticketsatisfaction'] ;
        $user = new User();
        $user->getFromDB(Session::getLoginUserID());

        $this->datas['##ticketsatisfaction.user##'] = $user->getName( ) ; //$this->getUserFullName( Session::getLoginUserID() );
        $this->datas['##ticketsatisfaction.ticket##'] = $locTicket->getID() ;
        $this->datas['##ticketsatisfaction.ticketname##'] = $locTicket->fields['name'];
        $this->datas['##ticketsatisfaction.url##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=ticket_".
                                       $locTicket->getID().'_Ticket$3');
        $this->datas['##ticketsatisfaction.date_begin##'] = $locTicketSatisfaction->fields['date_begin'];
        $this->datas['##ticketsatisfaction.date_answer##'] = $locTicketSatisfaction->fields['date_answered'];
        $this->datas['##ticketsatisfaction.satisfaction##'] = $locTicketSatisfaction->fields['satisfaction'];
        $this->datas['##ticketsatisfaction.comment##'] = $locTicketSatisfaction->fields['comment'];
        if(isset($options['item']->fields['friendliness'])) {
           $this->datas['##ticketsatisfaction.friendliness##'] = $locTicketSatisfaction->fields['friendliness'];
        }
        if( isset($options['item']->fields['responsetime'])) {
           $this->datas['##ticketsatisfaction.responsetime##'] = $locTicketSatisfaction->fields['responsetime'];
        }


        $this->getTags();
        foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->datas[$tag])) {
                $this->datas[$tag] = $values['label'];
            }
        }
    }


    function getTags() {
        global $LANG;
        foreach ($this->tags as $tag => $label) {
           if( ($tag != 'ticketsatisfaction.friendliness' && $tag != 'ticketsatisfaction.responsetime') || 
              (FieldExists( 'glpi_ticketsatisfactions', 'friendliness' ) && FieldExists('glpi_ticketsatisfactions', 'responsetime' )) ) {
               $this->addTagToList(array('tag'   => $tag,
                                      'label' => $LANG['plugin_arsurveys']["$tag"],
                                      'value' => true));
           }
        }

        asort($this->tag_descriptions);
    }

    //function getUserFullName( $id ) {
    //    $usr = new User;
    //    if( $usr->getFromDB( $id ) ) {
    //        return $usr->fields['realname'].", ".$usr->fields['firstname'] ;
    //    }

    //    return false ;
    //}

   /**
   * Summary of checkNotificationTarget
   * @param mixed $data 
   * @param mixed $options 
   */
   function checkNotificationTarget( $data, $options ) {
      // get ticket      
      $tick = $this->obj ;
      $members = array( ) ;
      $ids = array() ;

      $grp = new Group ;
      $grp->getFromDB( $data['items_id'] ) ;
      Group_User::getDataForGroup( $grp, $members, $ids ) ;

      // search if one at least ticket tech belongs to this group
      if( defined('CommonITILActor::ASSIGN') ) {
         $userType = constant('CommonITILActor::ASSIGN');
      } else {
         $userType = constant('CommonITILObject::ASSIGN');
      }
      foreach( $tick->getUsers( $userType ) as $tech ) {
         if( in_array( $tech['users_id'], $ids )  ) {
            // then send notification
            return true ;
            // no need to continue, one tech is enough to send notification to group manager
         }

      }
      return false ;
   }

   /**
    * Summary of checkNotificationThreshold
    * @param mixed $data 
    * @param mixed $options 
    * @return bool
    */
   function checkNotificationThreshold( $data, $options ) {
      $config = PluginArsurveysConfig::getInstance() ;
      $notif = new PluginArsurveysNotification ;
      if( !$notif->getFromDBByNotification( $data['notifications_id'] ) ){
         $notif->fields['threshold'] = null ;
         $notif->fields['force_positive_notif'] = null ;
      }
      switch( $this->raiseevent ) {
         case 'bad_survey' :
            $threshold = ($notif->fields['threshold']!=null ? $notif->fields['threshold'] : $config->fields['bad_threshold']);
            if( (in_array('satisfaction', $options['item']->updates) && $options['item']->input['satisfaction'] <= $threshold) ||
               (in_array( 'friendliness', $options['item']->updates ) && $options['item']->input['friendliness'] <= $threshold) ||
               (in_array( 'responsetime', $options['item']->updates ) && $options['item']->input['responsetime'] <= $threshold) ) {
               return true ;
            }
            break;
         case 'good_survey' :
            $threshold = ($notif->fields['threshold']!=null ? $notif->fields['threshold'] : $config->fields['good_threshold']);
            $force_positive_notif=($notif->fields['force_positive_notif']!=null ? $notif->fields['force_positive_notif'] : $config->fields['force_positive_notif']);
            if( (in_array('satisfaction', $options['item']->updates) || 
               in_array('friendliness', $options['item']->updates) || 
               in_array('responsetime', $options['item']->updates) ||
               in_array('comment', $options['item']->updates)) &&
               ($options['item']->input['satisfaction'] >= $threshold) &&
               (!isset( $options['item']->input['friendliness'] ) || $options['item']->input['friendliness'] >= $threshold) &&
               (!isset( $options['item']->input['responsetime'] ) || $options['item']->input['responsetime'] >= $threshold) &&
               ( $force_positive_notif || !empty($options['item']->input['comment']) )) {

               return true ;
            }
            break;
      }
      return false ;
   }

   /**
    * Summary of getAddressesByTarget
    * @param mixed $data 
    * @param mixed $options 
    */
   function getAddressesByTarget($data, $options = array()) {     
      $exec = true ;
      if( $data['type'] == Notification::SUPERVISOR_GROUP_TYPE && !$this->checkNotificationTarget( $data, $options ) ) {
         $exec = false ;
      }
      if( $exec && $this->checkNotificationThreshold( $data, $options) ) {
         parent::getAddressesByTarget( $data, $options = array() ) ;
      }
   }

}
