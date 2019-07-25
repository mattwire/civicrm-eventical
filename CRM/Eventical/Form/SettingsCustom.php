<?php
/*
 * http://civicrm.org/licensing
 */

use CRM_Eventical_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Eventical_Form_SettingsCustom extends CRM_Eventical_Form_Settings {

  /**
   * @param CRM_Core_Form $form
   * @param string $name
   * @param array $setting
   */
  public static function addSelectElement(&$form, $name, $setting) {
    switch ($name) {
      case 'url':
        $form->add(
          'select',
          $name,
          $setting['description'],
          [
            CRM_Event_Page_ICalendar::ICAL_URL_INFO => 'Link to event info page',
            CRM_Event_Page_ICalendar::ICAL_URL_REGISTER => 'Link to event register page',
          ]
        );
        break;
    }
  }

}
