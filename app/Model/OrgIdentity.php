<?php
/**
 * COmanage Registry OrgIdentity Model
 *
 * Copyright (C) 2010-13 University Corporation for Advanced Internet Development, Inc.
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
 * @copyright     Copyright (C) 2010-13 University Corporation for Advanced Internet Development, Inc.
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v0.2
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 * @version       $Id$
 */

class OrgIdentity extends AppModel {
  // Define class name for cake
  public $name = "OrgIdentity";
  
  // Current schema version for API
  public $version = "1.0";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $hasOne = array(
    // An Org Identity has one Primary Name, which is a pointer to a Name
    "PrimaryName" => array(
      'className'  => 'Name',
      'conditions' => array('PrimaryName.primary_name' => true),
      'dependent'  => false,
      'foreignKey' => 'org_identity_id'
    )
  );
  
  public $hasMany = array(
    // A person can have one or more address
    "Address" => array('dependent' => true),
    // An Org Identity can be attached to one or more CO Person
    // The current design requires all links to be dropped manually
    "CoOrgIdentityLink" => array('dependent' => false), 
    // A person can have various roles for a petition
    "CoPetition" => array(
      'dependent' => true,
      'foreignKey' => 'enrollee_org_identity_id'
    ),
    // A person can have one or more email address
    "EmailAddress" => array('dependent' => true),
    // It's probably not right to delete history records, but generally org identities shouldn't be deleted
    "HistoryRecord" => array('dependent' => true),
    // A person can have many identifiers within an organization
    "Identifier" => array('dependent' => true),
    "Name" => array('dependent' => true),
    // A person can have one or more telephone numbers
    "TelephoneNumber" => array('dependent' => true)
  );

  public $belongsTo = array(
    // A person may belong to an organization (if pre-defined)
    "Organization",
    // An Org Identity may belong to a CO, if not pooled
    "Co"
  );
  
  // Default display field for cake generated views
  public $displayField = "PrimaryName.family";
  
// XXX CO-296
  // Default ordering for find operations
//  public $order = array("Name.family", "Name.given");
  
  // Validation rules for table elements
  // Validation rules must be named 'content' for petition dynamic rule adjustment
  public $validate = array(
    'affiliation' => array(
      'content' => array(
        'rule' => array('inList', array(AffiliationEnum::Faculty,
                                        AffiliationEnum::Student,
                                        AffiliationEnum::Staff,
                                        AffiliationEnum::Alum,
                                        AffiliationEnum::Member,
                                        AffiliationEnum::Affiliate,
                                        AffiliationEnum::Employee,
                                        AffiliationEnum::LibraryWalkIn)),
        'required' => false,
        'allowEmpty' => true
      )
    ),
    'co_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true
      )
    ),
    'o' => array(
      'content' => array(
        'rule' => '/.*/',
        'required' => false,
        'allowEmpty' => true
      )
    ),
    'organization_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true
      )
    ),
    'ou' => array(
      'content' => array(
        'rule' => '/.*/',
        'required' => false,
        'allowEmpty' => true
      )
    ),
    'primary_name_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true
      )
    ),
    'title' => array(
      'content' => array(
        'rule' => '/.*/',
        'required' => false,
        'allowEmpty' => true
      )
    )
  );
  
  // Enum type hints
  
  public $cm_enum_txt = array(
    'affiliation' => 'en.affil',
  );
  
  public $cm_enum_types = array(
    'affiliation' => 'affil_t'
  );
  
  /**
   * Duplicate an Organizational Identity, including all of its related
   * (has one/has many) models.
   * - postcondition: Duplicate identity created.
   *
   * @since  COmanage Registry v0.2
   * @param  integer Identifier of Org Identity to duplicate
   * @param  integer CO to attach duplicate Org Identity to
   * @return integer New Org Identity ID if successful, -1 otherwise
   */
  
  public function duplicate($orgId, $coId)
  {
    $ret = -1;
    
    // We need deep recursion to pull the various related models. Track the previous
    // value so we can reset it after the find.
    $oldRecursive = $this->recursive;
    $this->recursive = 2;
    
    $src = $this->findById($orgId);
    
    $this->recursive = $oldRecursive;
    
    // Construct a new OrgIdentity explicitly copying the pieces we want (so as to
    // avoid any random cruft that recursive=2 happens to pull with it).
    
    $new = array();
    
    foreach(array_keys($src['OrgIdentity']) as $k)
    {
      // Copy most fields
      
      if($k != 'id' && $k != 'co_id' && $k != 'created' && $k != 'modified')
        $new['OrgIdentity'][$k] = $src['OrgIdentity'][$k];
    }
    
    // Set the CO ID
    $new['OrgIdentity']['co_id'] = $coId;
    
    // Copy most fields from most dependent models.
    
    foreach(array_keys($this->hasOne) as $m)
    {
      if($this->hasOne[$m]['dependent'])
      {
        foreach(array_keys($src[$m]) as $k)
        {
          if($k != 'id' && $k != 'created' && $k != 'modified')
            $new[$m][$k] = $src[$m][$k];
        }
      }
    }
    
    foreach(array_keys($this->hasMany) as $m)
    {
      if($this->hasMany[$m]['dependent'] && $m != 'CoPetition')
      {
        foreach(array_keys($src[$m]) as $k)
        {
          if($k != 'id' && $k != 'created' && $k != 'modified')
            $new[$m][$k] = $src[$m][$k];
        }
      }
    }
    
    $this->create();
    $this->saveAll($new);
    $ret = $this->id;
    
    return($ret);
  }
  
  /**
   * Determine which COs $id is eligible to be linked into. ie: Return the set of
   * COs $id is not a member of.
   *
   * @since  COmanage Registry v0.9.1
   * @param  Integer Org Identity ID
   * @return Array Array of CO IDs and CO name
   */
  
  public function linkableCos($id) {
    $cos = array();
    
    $CmpEnrollmentConfiguration = ClassRegistry::init('CmpEnrollmentConfiguration');
    
    if($CmpEnrollmentConfiguration->orgIdentitiesPooled()) {
      // First pull the set of COs that $id is in
      
      $args = array();
      $args['joins'][0]['table'] = 'co_org_identity_links';
      $args['joins'][0]['alias'] = 'CoOrgIdentityLink';
      $args['joins'][0]['type'] = 'INNER';
      $args['joins'][0]['conditions'][0] = 'CoPerson.id=CoOrgIdentityLink.co_person_id';
      $args['conditions']['CoOrgIdentityLink.org_identity_id'] = $id;
      $args['fields'] = array('CoPerson.co_id', 'CoPerson.id');
      $args['contain'] = false;
      
      $inCos = $this->Co->CoPerson->find('list', $args);
      
      // Then pull the set of COs that aren't listed above
      
      $args = array();
      $args['conditions']['NOT']['Co.id'] = array_keys($inCos);
      $args['fields'] = array('Co.id', 'Co.name');
      $args['contain'] = false;
      
      $cos = $this->Co->find('list', $args);
    } else {
      // Pull the Org Identity, it's CO, and it's Link via containable. If no link,
      // then this CO is linkable.
      
      $args = array();
      $args['conditions']['OrgIdentity.id'] = $id;
      $args['contain'][] = 'Co';
      $args['contain'][] = 'CoOrgIdentityLink';
      
      $orgid = $this->find('first', $args);
      
      if(empty($orgid['CoOrgIdentityLink'])) {
        $cos[ $orgid['Co']['id'] ] = $orgid['Co']['name'];
      }
    }
    // determine if pooled
    // if not, pull list of cos
    // for each CO, see if a link exists from $id to a co person in that CO
    
    return $cos;
  }

  /**
   * Pool Organizational Identities. This will delete all links from Org Identities
   * to COs. No attempt is made to delete duplicate identities that may result from
   * this operation. This operation cannot be undone.
   * - precondition: Organizational Identities are not pooled
   * - postcondition: co_id values for Org Identities are deleted
   *
   * @since  COmanage Registry v0.2
   * @return boolean True if successful, false otherwise
   */
  
  public function pool()
  {
    return($this->updateAll(array('OrgIdentity.co_id' => null)));
  }
  
  /**
   * Unpool Organizational Identities. This will link organizational identities
   * to the COs which use them. If an Org Identity is referenced by more than
   * one CO, it will be duplicated.
   * - precondition: Organizational Identities are pooled
   * - postcondition: co_id values for Org Identities are assigned. If necessary, org identities will be duplicated
   *
   * @since  COmanage Registry v0.2
   * @return boolean True if successful, false otherwise
   */
  
  function unpool()
  {
    // Retrieve all CO/Org Identity Links.
    
    $links = $this->CoOrgIdentityLink->find('all');
    
    // For each retrieved record, find the CO ID for the CO Identity and
    // attach it to the Org Identity.
    
    foreach($links as $l)
    {
      $coId = $l['CoPerson']['co_id'];
      $orgId = $l['CoOrgIdentityLink']['org_identity_id'];
      
      // Get the latest version of the Org Identity, even though it's available
      // in $links
      
      $o = $this->findById($orgId);
      
      if(!isset($o['OrgIdentity']['co_id']) || !$o['OrgIdentity']['co_id'])
      {
        // co_id not yet set (ie: this org_identity is not yet linked to a CO),
        // so we can just update this record
        
        $this->id = $orgId;
        // Use co_id here and NOT OrgIdentity.co_id (per the docs)
        $this->saveField('co_id', $coId);
      }
      else
      {
        // We've previously seen this org identity. First check to see if we've
        // attached it to the same CO. (This shouldn't really happen since it
        // implies the same person was added twice to the same CO.) If so, there's
        // nothing to do.
        
        if($o['OrgIdentity']['co_id'] != $coId)
        {
          // Not the same CO. We need to duplicate the OrgIdentity (including all
          // of it's dependent attributes like identifiers) and relink to the newly
          // created identity.
          
          $newOrgId = $this->duplicate($orgId, $coId);
          
          if($newOrgId != -1)
          {
            // Update CoOrgIdentityLink
            
            $this->CoOrgIdentityLink->id = $l['CoOrgIdentityLink']['id'];
            $this->CoOrgIdentityLink->saveField('org_identity_id', $newOrgId);
          }
          else
          {
            return(false);
          }
        }
      }
    }
    
    return(true);
  }
}
