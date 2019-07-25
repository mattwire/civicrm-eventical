<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2019
 * $Id$
 *
 */

/**
 * ICalendar class
 *
 */
class CRM_Event_Page_ICalendar extends CRM_Core_Page {

  const ICAL_URL_INFO = 0;
  const ICAL_URL_REGISTER = 1;

  /**
   * Heart of the iCalendar data assignment process. The runner gets all the meta
   * data for the event and calls the  method to output the iCalendar
   * to the user. If gData param is passed on the URL, outputs gData XML format.
   * Else outputs iCalendar format per IETF RFC2445. Page param true means send
   * to browser as inline content. Else, we send .ics file as attachment.
   *
   * @return void
   */
  public function run() {
    $id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, NULL, 'GET');
    $type = CRM_Utils_Request::retrieve('type', 'Positive', $this, FALSE, 0);
    $start = CRM_Utils_Request::retrieve('start', 'Positive', $this, FALSE, 0);
    $end = CRM_Utils_Request::retrieve('end', 'Positive', $this, FALSE, 0);
    $iCalPage = CRM_Utils_Request::retrieve('list', 'Positive', $this, FALSE, 0);
    $gData = CRM_Utils_Request::retrieve('gData', 'Positive', $this, FALSE, 0);
    $html = CRM_Utils_Request::retrieve('html', 'Positive', $this, FALSE, 0);
    $rss = CRM_Utils_Request::retrieve('rss', 'Positive', $this, FALSE, 0);

    $info = self::getCompleteInfo($start, $type, $id, $end);
    $this->assign('events', $info);
    $this->assign('timezone', @date_default_timezone_get());

    // Send data to the correct template for formatting (iCal vs. gData)
    $template = CRM_Core_Smarty::singleton();
    $config = CRM_Core_Config::singleton();
    if ($rss) {
      // rss 2.0 requires lower case dash delimited locale
      $this->assign('rssLang', str_replace('_', '-', strtolower($config->lcMessages)));
      $calendar = $template->fetch('CRM/Core/Calendar/Rss.tpl');
    }
    elseif ($gData) {
      $calendar = $template->fetch('CRM/Core/Calendar/GData.tpl');
    }
    elseif ($html) {
      // check if we're in shopping cart mode for events
      $enable_cart = Civi::settings()->get('enable_cart');
      if ($enable_cart) {
        $this->assign('registration_links', TRUE);
      }
      return parent::run();
    }
    else {
      $calendar = $template->fetch('CRM/Core/Calendar/ICal.tpl');
      $calendar = preg_replace('/(?<!\r)\n/', "\r\n", $calendar);
    }

    // Push output for feed or download
    if ($iCalPage == 1) {
      if ($gData || $rss) {
        CRM_Utils_ICalendar::send($calendar, 'text/xml', 'utf-8');
      }
      else {
        CRM_Utils_ICalendar::send($calendar, 'text/plain', 'utf-8');
      }
    }
    else {
      CRM_Utils_ICalendar::send($calendar, 'text/calendar', 'utf-8', 'civicrm_ical.ics', 'attachment');
    }
    CRM_Utils_System::civiExit();
  }

  /**
   * Get the complete information for one or more events.
   * @fixme: Copied from CRM/Event/BAO/Event.php
   *
   * @param date $start
   *   Get events with start date >= this date.
   * @param int $type Get events on the a specific event type (by event_type_id).
   *   Get events on the a specific event type (by event_type_id).
   * @param int $eventId Return a single event - by event id.
   *   Return a single event - by event id.
   * @param date $end
   *   Also get events with end date >= this date.
   * @param bool $onlyPublic Include public events only, default TRUE.
   *   Include public events only, default TRUE.
   *
   * @return array
   *   array of all the events that are searched
   */
  public static function &getCompleteInfo(
    $start = NULL,
    $type = NULL,
    $eventId = NULL,
    $end = NULL,
    $onlyPublic = TRUE
  ) {
    $publicCondition = NULL;
    if ($onlyPublic) {
      $publicCondition = "  AND civicrm_event.is_public = 1";
    }

    $dateCondition = '';
    // if start and end date are NOT passed, return all events with start_date OR end_date >= today CRM-5133
    if ($start) {
      // get events with start_date >= requested start
      $startDate = CRM_Utils_Type::escape($start, 'Date');
      $dateCondition .= " AND ( civicrm_event.start_date >= {$startDate} )";
    }

    if ($end) {
      // also get events with end_date <= requested end
      $endDate = CRM_Utils_Type::escape($end, 'Date');
      $dateCondition .= " AND ( civicrm_event.end_date <= '{$endDate}' ) ";
    }

    // CRM-9421 and CRM-8620 Default mode for ical/rss feeds. No start or end filter passed.
    // Need to exclude old events with only start date
    // and not exclude events in progress (start <= today and end >= today). DGG
    if (empty($start) && empty($end)) {
      // get events with end date >= today, not sure of this logic
      // but keeping this for backward compatibility as per issue CRM-5133
      $today = date("Y-m-d G:i:s");
      $dateCondition .= " AND ( civicrm_event.end_date >= '{$today}' OR civicrm_event.start_date >= '{$today}' ) ";
    }

    if ($type) {
      $typeCondition = " AND civicrm_event.event_type_id = " . CRM_Utils_Type::escape($type, 'Integer');
    }

    // Get the Id of Option Group for Event Types
    $optionGroupDAO = new CRM_Core_DAO_OptionGroup();
    $optionGroupDAO->name = 'event_type';
    $optionGroupId = NULL;
    if ($optionGroupDAO->find(TRUE)) {
      $optionGroupId = $optionGroupDAO->id;
    }

    $query = "
SELECT
  civicrm_event.id as event_id,
  civicrm_email.email as email,
  civicrm_event.title as title,
  civicrm_event.summary as summary,
  civicrm_event.start_date as start,
  civicrm_event.end_date as end,
  civicrm_event.description as description,
  civicrm_event.is_show_location as is_show_location,
  civicrm_event.is_online_registration as is_online_registration,
  civicrm_event.registration_link_text as registration_link_text,
  civicrm_event.registration_start_date as registration_start_date,
  civicrm_event.registration_end_date as registration_end_date,
  civicrm_option_value.label as event_type,
  civicrm_address.name as address_name,
  civicrm_address.street_address as street_address,
  civicrm_address.supplemental_address_1 as supplemental_address_1,
  civicrm_address.supplemental_address_2 as supplemental_address_2,
  civicrm_address.supplemental_address_3 as supplemental_address_3,
  civicrm_address.city as city,
  civicrm_address.postal_code as postal_code,
  civicrm_address.postal_code_suffix as postal_code_suffix,
  civicrm_state_province.abbreviation as state,
  civicrm_country.name AS country
FROM civicrm_event
LEFT JOIN civicrm_loc_block ON civicrm_event.loc_block_id = civicrm_loc_block.id
LEFT JOIN civicrm_address ON civicrm_loc_block.address_id = civicrm_address.id
LEFT JOIN civicrm_state_province ON civicrm_address.state_province_id = civicrm_state_province.id
LEFT JOIN civicrm_country ON civicrm_address.country_id = civicrm_country.id
LEFT JOIN civicrm_email ON civicrm_loc_block.email_id = civicrm_email.id
LEFT JOIN civicrm_option_value ON (
                                    civicrm_event.event_type_id = civicrm_option_value.value AND
                                    civicrm_option_value.option_group_id = %1 )
WHERE civicrm_event.is_active = 1
      AND (is_template = 0 OR is_template IS NULL)
      {$publicCondition}
      {$dateCondition}";

    if (isset($typeCondition)) {
      $query .= $typeCondition;
    }

    if (isset($eventId)) {
      $query .= " AND civicrm_event.id =$eventId ";
    }
    $query .= " ORDER BY   civicrm_event.start_date ASC";

    $params = [1 => [$optionGroupId, 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    $all = [];
    $config = CRM_Core_Config::singleton();

    $baseURL = parse_url($config->userFrameworkBaseURL);
    $url = "@" . $baseURL['host'];
    if (!empty($baseURL['path'])) {
      $url .= substr($baseURL['path'], 0, -1);
    }

    // check 'view event info' permission
    //@todo - per CRM-14626 we have resolved that 'view event info' means 'view ALL event info'
    // and passing in the specific permission here will short-circuit the evaluation of permission to
    // see specific events (doesn't seem relevant to this call
    // however, since this function is accessed only by a convoluted call from a joomla block function
    // it seems safer not to touch here. Suggestion is that CRM_Core_Permission::check(array or relevant permissions) would
    // be clearer & safer here
    $permissions = CRM_Core_Permission::event(CRM_Core_Permission::VIEW);

    // check if we're in shopping cart mode for events
    $enable_cart = Civi::settings()->get('enable_cart');
    if ($enable_cart) {
    }
    while ($dao->fetch()) {
      if (!empty($permissions) && in_array($dao->event_id, $permissions)) {
        $info = [];
        $info['uid'] = "CiviCRM_EventID_{$dao->event_id}_" . md5($config->userFrameworkBaseURL) . $url;

        $info['title'] = $dao->title;
        $info['event_id'] = $dao->event_id;
        $info['summary'] = $dao->summary;
        $info['description'] = $dao->description;
        $info['start_date'] = $dao->start;
        $info['end_date'] = $dao->end;
        $info['contact_email'] = $dao->email;
        $info['event_type'] = $dao->event_type;
        $info['is_show_location'] = $dao->is_show_location;
        $info['is_online_registration'] = $dao->is_online_registration;
        $info['registration_link_text'] = $dao->registration_link_text;
        $info['registration_start_date'] = $dao->registration_start_date;
        $info['registration_end_date'] = $dao->registration_end_date;

        $address = '';

        $addrFields = [
          'address_name' => $dao->address_name,
          'street_address' => $dao->street_address,
          'supplemental_address_1' => $dao->supplemental_address_1,
          'supplemental_address_2' => $dao->supplemental_address_2,
          'supplemental_address_3' => $dao->supplemental_address_3,
          'city' => $dao->city,
          'state_province' => $dao->state,
          'postal_code' => $dao->postal_code,
          'postal_code_suffix' => $dao->postal_code_suffix,
          'country' => $dao->country,
          'county' => NULL,
        ];

        CRM_Utils_String::append($address, ', ',
          CRM_Utils_Address::format($addrFields)
        );
        $info['location'] = $address;
        switch ((int) CRM_Eventical_Settings::getValue('eventical_url')) {
          case CRM_Event_Page_ICalendar::ICAL_URL_REGISTER:
            $info['url'] = CRM_Utils_System::url('civicrm/event/register', 'reset=1&id=' . $dao->event_id, TRUE, NULL, FALSE);
            break;

          case CRM_Event_Page_ICalendar::ICAL_URL_INFO:
          default:
            $info['url'] = CRM_Utils_System::url('civicrm/event/info', 'reset=1&id=' . $dao->event_id, TRUE, NULL, FALSE);
        }

        if ($enable_cart) {
          $reg = CRM_Event_Cart_BAO_EventInCart::get_registration_link($dao->event_id);
          $info['registration_link'] = CRM_Utils_System::url($reg['path'], $reg['query'], TRUE);
          $info['registration_link_text'] = $reg['label'];
        }

        $all[] = $info;
      }
    }

    return $all;
  }

}
