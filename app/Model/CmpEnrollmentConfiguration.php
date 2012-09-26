<?php
/**
 * COmanage Registry CMP Enrollment Configuration Model
 *
 * Copyright (C) 2011-12 University Corporation for Advanced Internet Development, Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under
 * the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 * @copyright     Copyright (C) 2011-12 University Corporation for Advanced Internet Development, Inc.
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v0.3
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 * @version       $Id$
 */

class CmpEnrollmentConfiguration extends AppModel {
  // Define class name for cake
  public $name = "CmpEnrollmentConfiguration";
  
  // Current schema version for API
  public $version = "1.0";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $hasMany = array("CmpEnrollmentAttribute" =>   // A CMP Enrollment Configuration has many CMP Enrollment Attributes
                       array('dependent' => true));
  
  // Default display field for cake generated views
  public $displayField = "name";
  
  // Default ordering for find operations
  public $order = array("name");
  
  // Validation rules for table elements
  public $validate = array(
    'name' => array(
      'rule' => 'notEmpty',
      'required' => true,
      'message' => 'A name must be provided'
    ),
    'attrs_from_ldap' => array(
      'rule' => array('boolean')
    ),
    'attrs_from_saml' => array(
      'rule' => array('boolean')
    ),
    'status' => array(
      'rule' => array('inList', array(StatusEnum::Active,
                                      StatusEnum::Suspended))
    ),
    'pool_org_identities' => array(
      'rule' => array('boolean')
    )
  );
  
  /**
   * Find the default (ie: active) CMP Enrollment Configuration for this platform.
   * - precondition: Initial setup (performed by select()) has been completed.
   *
   * @since  COmanage Registry v0.3
   * @return Array Of the form returned by find()
   */
  
  public function findDefault() {
    return($this->find('first',
                       array('conditions' =>
                             array('CmpEnrollmentConfiguration.name' => 'CMP Enrollment Configuration',
                                   'CmpEnrollmentConfiguration.status' => StatusEnum::Active))));
  }
  
  /**
   * Obtain the standard order for rendering lists of attributes.
   *
   * @since  COmanage Registry v0.3
   * @param  Model Calling model, used to determine associations
   * @return Array Array of arrays, each of which defines 'attr', 'type', and 'label'
   */
  
  public function getStandardAttributeOrder($model=null) {
    global $cm_lang, $cm_texts;
    
    // This is a function rather than a var so _txt evaluates.
    // The attributes in this list need to be kept in sync with the controller (select()).
    
    if(isset($model))
    {
      // Determine association types so appropriate form elements can be rendered
      $address_assoc = (isset($model->hasOne['Address'])
                        ? 'hasone'
                        : (isset($model->hasMany['Address']) ? 'hasmany' : null));
      $email_assoc = (isset($model->hasOne['EmailAddress'])
                      ? 'hasone'
                      : (isset($model->hasMany['EmailAddress']) ? 'hasmany' : null));
      $id_assoc = (isset($model->hasOne['Identifier'])
                   ? 'hasone'
                   : (isset($model->hasMany['Identifier']) ? 'hasmany' : null));
      $name_assoc = (isset($model->hasOne['Name'])
                     ? 'hasone'
                     : (isset($model->hasMany['Name']) ? 'hasmany' : null));
      $phone_assoc = (isset($model->hasOne['TelephoneNumber'])
                      ? 'hasone'
                      : (isset($model->hasMany['TelephoneNumber']) ? 'hasmany' : null));
    }
    else
    {
      $address_assoc = null;
      $email_assoc = null;
      $id_assoc = null;
      $name_assoc = null;
      $phone_assoc = null;
    }
    
    return(array(
      array('attr' => 'names:honorific',
            'type' => NameEnum::Official,
            'label' => _txt('fd.name.honorific'),
            'desc' => _txt('fd.name.h.desc'),
            'assoc' => $name_assoc),
      array('attr' => 'names:given',
            'type' => NameEnum::Official,
            'label' => _txt('fd.name.given'),
            'assoc' => $name_assoc),
      array('attr' => 'names:middle',
            'type' => NameEnum::Official,
            'label' => _txt('fd.name.middle'),
            'assoc' => $name_assoc),
      array('attr' => 'names:family',
            'type' => NameEnum::Official,
            'label' => _txt('fd.name.family'),
            'assoc' => $name_assoc),
      array('attr' => 'names:suffix',
            'type' => NameEnum::Official,
            'label' => _txt('fd.name.suffix'),
            'desc' => _txt('fd.name.s.desc'),
            'assoc' => $name_assoc),
      array('attr' => 'affiliation',
            'type' => null,
            'label' => _txt('fd.affiliation'),
            'select' => array('options' => $cm_texts[ $cm_lang ]['en.affil'],
                              'default' => 'member')),
      array('attr' => 'title',
            'type' => null,
            'label' => _txt('fd.title')),
      array('attr' => 'o',
            'type' => null,
            'label' => _txt('fd.o')),
      array('attr' => 'ou',
            'type' => null,
            'label' => _txt('fd.ou')),
      array('attr' => 'identifiers:identifier',
            'type' => IdentifierEnum::ePPN,
            'label' => _txt('en.identifier', null, IdentifierEnum::ePPN),
            'assoc' => $id_assoc),
      array('attr' => 'email_addresses:mail',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.email_address.mail'),
            'assoc' => $email_assoc),
      array('attr' => 'telephone_numbers:number',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.telephone_number.number'),
            'assoc' => $phone_assoc),
      array('attr' => 'addresses:line1',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.address.line1'),
            'assoc' => $address_assoc),
      array('attr' => 'addresses:line2',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.address.line2'),
            'assoc' => $address_assoc),
      array('attr' => 'addresses:locality',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.address.locality'),
            'assoc' => $address_assoc),
      array('attr' => 'addresses:state',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.address.state'),
            'assoc' => $address_assoc),
      array('attr' => 'addresses:postal_code',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.address.postal_code'),
            'assoc' => $address_assoc),
      array('attr' => 'addresses:country',
            'type' => ContactEnum::Office,
            'label' => _txt('fd.address.country'),
            'assoc' => $address_assoc)
    ));
  }
  
  /**
   * Determine if organizational identities may be provided by CO enrollment
   * flows in the default (ie: active) CMP Enrollment Configuration for this platform.
   * - precondition: Initial setup (performed by select()) has been completed
   *
   * @since  COmanage Registry v0.5
   * @return boolean True if org identities may be provided by CO enrollment flows, false otherwise
   */
  
  public function orgIdentitiesFromCOEF() {
    $r = $this->find('first',
                     array('conditions' =>
                           array('CmpEnrollmentConfiguration.name' => 'CMP Enrollment Configuration',
                                 'CmpEnrollmentConfiguration.status' => StatusEnum::Active),
                           // We don't need to pull attributes, just the configuration
                           'contain' => false,
                           'fields' =>
                           array('CmpEnrollmentConfiguration.attrs_from_coef')));
    
    return($r['CmpEnrollmentConfiguration']['attrs_from_coef']);
  }
  
  /**
   * Determine if organizational identities are pooled in the default (ie: active)
   * CMP Enrollment Configuration for this platform.
   * - precondition: Initial setup (performed by select()) has been completed
   *
   * @since  COmanage Registry v0.3
   * @return boolean True if org identities are pooled, false otherwise
   */
  
  public function orgIdentitiesPooled() {
    $r = $this->find('first',
                     array('conditions' =>
                           array('CmpEnrollmentConfiguration.name' => 'CMP Enrollment Configuration',
                                 'CmpEnrollmentConfiguration.status' => StatusEnum::Active),
                           // We don't need to pull attributes, just the configuration
                           'contain' => false,
                           'fields' =>
                           array('CmpEnrollmentConfiguration.pool_org_identities')));
    
    return($r['CmpEnrollmentConfiguration']['pool_org_identities']);
  }
}