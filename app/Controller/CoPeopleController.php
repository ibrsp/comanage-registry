<?php
/**
 * COmanage Registry CO People Controller
 *
 * Copyright (C) 2010-12 University Corporation for Advanced Internet Development, Inc.
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
 * @copyright     Copyright (C) 2010-12 University Corporation for Advanced Internet Development, Inc.
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v0.1
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 * @version       $Id$
 */

App::uses("StandardController", "Controller");

class CoPeopleController extends StandardController {
  public $name = "CoPeople";
  
  public $helpers = array('Time');
  
  // When using additional controllers, we must also specify our own
  public $uses = array('CoPerson', 'CmpEnrollmentConfiguration');
  
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'Name.family' => 'asc',
      'Name.given' => 'asc'
    )
  );
  
  // This controller needs a CO to be set
  public $requires_co = true;

  // For CO Person group renderings, we need all CoGroup data, so we need more recursion
  public $edit_recursion = 2;
  public $view_recursion = 2;
  // We also need Name on delete
  public $delete_recursion = 2;
  
  /**
   * Callback before other controller methods are invoked or views are rendered.
   * - postcondition: $pool_org_identities set
   * - postcondition: $sponsors set
   *
   * @since  COmanage Registry v0.1
   */
  
  function beforeFilter() {
    // This controller may or may not require a CO, depending on how
    // the CMP Enrollment Configuration is set up. Check and adjust before
    // beforeFilter is called.
    
    // We need this to render links to the org identity (which may or may
    // not need the co id carried).
    
    $this->set('pool_org_identities', $this->CmpEnrollmentConfiguration->orgIdentitiesPooled());
    
    parent::beforeFilter();

    // generate list of sponsors
    $this->set('sponsors', $this->CoPerson->sponsorList());
  }
  
  /**
   * Perform any dependency checks required prior to a delete operation.
   * - postcondition: Session flash message updated (HTML) or HTTP status returned (REST)
   *
   * @since  COmanage Registry v0.1
   * @param  Array Current data
   * @return boolean true if dependency checks succeed, false otherwise.
   */
  
  function checkDeleteDependencies($curdata) {
    // Check if the target person is a member of any COU that the current user
    // does not have permissions over. If so, fail.
    
    foreach($curdata['CoPersonRole'] as $pr)
    {
      if(isset($pr['cou_id']) && $pr['cou_id'] != "")
      {
        if(!isset($this->viewVars['permissions']['cous'][ $pr['cou_id'] ]))
        {
          // Find the name of the COU
          
          $couname = "(?)";
          
          foreach($this->cur_co['Cou'] as $cou) {
            if($cou['id'] == $pr['cou_id']) {
              $couname = $cou['name'];
              break;
            }
          }
          
          $this->Session->setFlash(_txt('er.coumember',
                                   array(generateCn($curdata['Name']),
                                         Sanitize::html($couname))),
                                   '', array(), 'error');
          
          return(false);
        }
      }
    }
    
    return(true);
  }

  /**
   * Perform any dependency checks required prior to a write (add/edit) operation.
   * - postcondition: Session flash message updated (HTML) or HTTP status returned (REST)
   *
   * @since  COmanage Registry v0.1
   * @param  Array Request data
   * @param  Array Current data
   * @return boolean true if dependency checks succeed, false otherwise.
   */

  function checkWriteDependencies($reqdata, $curdata = null) {
    // Check that an org identity being added is not already a member of the CO.
    // (A person can't be added to the same CO twice... that's what Person Roles
    // are for.) Note the REST check is in co_org_identity_links_controller.
      
    if(!$this->restful
       && (!$curdata
           ||
           ($reqdata['CoOrgIdentityLink'][0]['org_identity_id']
            != $curdata['CoOrgIdentityLink'][0]['org_identity_id'])))
    {
      if($this->CoPerson->orgIdIsCoPerson($this->cur_co['Co']['id'],
                                          $reqdata['CoOrgIdentityLink'][0]['org_identity_id']))
      {
        $this->Session->setFlash(_txt('er.cop.member',
                                 array(generateCn($this->data['Name']),
                                       $this->cur_co['Co']['name'])),
                                 '', array(), 'error');
        
        $redirect['controller'] = 'co_people';
        $redirect['action'] = 'index';
        $redirect['co'] = $this->cur_co['Co']['id'];
        $this->redirect($redirect);
        
        // We won't actually accomplish anything with this return
        return(false);
      }
    }
    
    return(true);
  }
  
  /**
   * Retrieve CO and Org attributes for comparison.
   * - precondition: <id> must exist
   * - postcondition: $<object>s set (with one member) if found
   * - postcondition: HTTP status returned (REST)
   * - postcondition: Session flash message updated (HTML) on suitable error
   *
   * @since  COmanage Registry v0.1
   * @param  Integer CO Person identifier
   */
  
  function compare($id) {
    // This is pretty similar to the standard view or edit methods.
    // We'll just retrieve and set the Org Person, then invoke view.
    // (We could invoke edit instead, presumably.)
    
    $orgp = $this->CoPerson->CoOrgIdentityLink->OrgIdentity->find("all",
                                                                  array("conditions" =>
                                                                        array('CoOrgIdentityLink.co_person_id' => $id),
                                                                        "joins" =>
                                                                        array(array('table' => 'co_org_identity_links',
                                                                                    'alias' => 'CoOrgIdentityLink',
                                                                                    'type' => 'INNER',
                                                                                    'conditions' => array('OrgIdentity.id=CoOrgIdentityLink.org_identity_id')))));
    
    if(!empty($orgp))
    {
      $this->set("org_identities", $orgp);
      
      $this->view($id);
    }
  }
  
  /**
   * Generate a display key to be used in messages such as "Item Added".
   *
   * @since  COmanage Registry v0.1
   * @param  Array A cached object (eg: from prior to a delete)
   * @return string A string to be included for display.
   */
 
  function generateDisplayKey($c = null) {
    // Get a pointer to our model
    $req = $this->modelClass;
    $model = $this->$req;

    if(isset($c[$req][$model->displayField]))
      return($c[$req][$model->displayField]);
    if(isset($this->request->data['Name']))
      return(generateCn($this->request->data['Name']));
    if(isset($c['Name']))
      return(generateCn($c['Name']));
    else
      return("(?)");
  }

  /**
   * Invite the person identified by the Org Identity to a CO.
   * - precondition: orgidentity and co set in $this->request->params
   * - postcondition: $co_people set
   * - postcondition: ID of CO to invite to
   *
   * @since  COmanage Registry v0.1
   */
  
  function invite() {
    $orgp = $this->CoPerson->CoOrgIdentityLink->OrgIdentity->findById($this->request->params['named']['orgidentityid']);
    
    if(!empty($orgp))
    {
      if(!$this->restful)
      {
        // Set page title
        $this->set('title_for_layout', _txt('op.inv-a', array(generateCn($orgp['Name']))));
      }

      // Construct a CoPerson from the OrgIdentity.  We only populate defaulted values.
      
      $cop['Name'] = $orgp['Name'];
      $cop['CoOrgIdentityLink'][0]['org_identity_id'] = $orgp['OrgIdentity']['id'];
      
      $this->set('co_people', array(0 => $cop));
    }
    else
    {
      $this->Session->setFlash(_txt('op.orgp-unk-a', array($this->request->params['named']['orgidentityid'])), '', array(), 'error');
      $this->redirect(array('action' => 'index', 'co' => $this->cur_co['Co']['id']));
    }
  }

  /**
   * Add the person identified by the Org Identity to the identified CO.
   * - precondition: org_identity_id and co_id set in $this->request->params
   *
   * @since  COmanage Registry v0.4
   * @return void
   */

  function add() {
   // The parent add() method is overridden here so that for non REST calls
   // the validation can be relaxed to allow the normal saveAll() call 
   // in the parent add() method to complete without a validation error.
   // If the validation is not relaxed so that co_person_id and org_identity_id
   // are not required then the validation of all the tables before any updates
   // will cause a validation error for the model CoOrgIdentityLink.

    if(!$this->restful) {
      $this->loadModel('CoOrgIdentityLink');

      // dynamically relax the validation for the CoOrgIdentityLink model
      $this->CoOrgIdentityLink->validate['co_person_id']['required'] = false;
      $this->CoOrgIdentityLink->validate['org_identity_id']['required'] = false;
    }

    // call the add() method for the parent StandardController class
    parent::add();
  }

  /**
   * Authorization for this Controller, called by Auth component
   * - precondition: Session.Auth holds data used for authz decisions
   * - postcondition: $permissions set with calculated permissions
   *
   * @since  COmanage Registry v0.1
   * @return Array Permissions
   */
  
  function isAuthorized() {
    $cmr = $this->calculateCMRoles();
    
    // Is this our own record?
    $self = false;
    
    if($cmr['comember'] && $cmr['copersonid'] &&
       ((isset($this->params['pass'][0]) && ($cmr['copersonid'] == $this->params['pass'][0]))
        ||
        ($this->action == 'editself' && isset($cmr['copersonid']))))
      $self = true;

    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    
    // Determine what operations this user can perform
    
    // Add a new CO Person?
    $p['add'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $cmr['subadmin']);
    // Via invite?
    $p['invite'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $cmr['subadmin']);
    
    // Compare CO attributes and Org attributes?
    $p['compare'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $self);
    
    // Delete an existing CO Person?
    // A COU admin should be able to delete a CO Person, but not if they have any roles
    // associated with a COU the admin isn't responsible for. We'll catch that in
    // checkDeleteDependencies.
    $p['delete'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $cmr['subadmin']);
    
    // Edit an existing CO Person?
    $p['edit'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $cmr['subadmin'] || $self);

    // Are we allowed to edit our own record?
    // If we're an admin, we act as an admin, not self.
    $p['editself'] = $self && !$cmr['cmadmin'] && !$cmr['coadmin'] && !$cmr['subadmin'];
    
    // View all existing CO People (or a COU's worth)?
    $p['index'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $cmr['subadmin']);
    
    // View an existing CO Person?
    $p['view'] = ($cmr['cmadmin'] || $cmr['coadmin'] || $cmr['subadmin'] || $self);
    
    // Determine which COUs a person can manage.
    
    if($cmr['cmadmin'] || $cmr['coadmin'])
      $p['cous'] = $this->CoPerson->CoPersonRole->Cou->find("list",
                                                            array("conditions" =>
                                                                  array("co_id" => $this->cur_co['Co']['id'])));      
    elseif($cmr['subadmin'])
      $p['cous'] = $this->CoPerson->CoPersonRole->Cou->find("list",
                                                            array("conditions" =>
                                                                  array("co_id" => $this->cur_co['Co']['id'],
                                                                        "name" => $cmr['couadmin'])));
    else
      $p['cous'] = array();
      
    // COUs are handled a bit differently. We need to authorize operations that
    // operate on a per-person basis accordingly.
    
    if($cmr['subadmin'] && !empty($p['cous']))
    {
      if(!empty($this->request->params['pass'][0]))
      {
        // If the target person is in a COU managed by the COU admin, grant permission
        
        $tcous = $this->CoPerson->CoPersonRole->Cou->find("list",
                                                          array("joins" =>
                                                                array(array('table' => 'co_person_roles',
                                                                            'alias' => 'CoPersonRole',
                                                                            'type' => 'INNER',
                                                                            'conditions' => array('Cou.id=CoPersonRole.cou_id'))),
                                                                "conditions" =>
                                                                array('CoPersonRole.co_person_id' => $this->request->params['pass'][0])));
        
        $a = array_intersect($tcous, $p['cous']);

        if(!empty($a))
        {
          // CO Person is a member of at least one COU that the COU admin manages
          
          $p['compare'] = true;
          $p['delete'] = true;
          $p['edit'] = true;
          $p['view'] = true;
        }
      }
      else
      {
        if($p['index'])
        {
          // These permissions are person-level, and are probably not exactly right.
          // Specifically, delete could be problematic since a COU admin can't
          // delete a person with a COU role that the admin doesn't manage.
          // For now, we'll catch that in checkDeleteDependencies.
          
          $p['compare'] = true;
          $p['delete'] = true;
          $p['edit'] = true;
          $p['view'] = true;
        }
      }
    }
    
    $this->set('permissions', $p);
    return($p[$this->action]);
  }

  /**
   * Perform a redirect back to the controller's default view.
   * - postcondition: Redirect generated
   *
   * @since  COmanage Registry v0.1
   */
  
  function performRedirect() {
    // On add, redirect to send view for notification of invite
          
    if($this->action == 'add')
      $this->redirect(array('controller' => 'co_invites',
                            'action' => 'send',
                            'copersonid' => $this->CoPerson->id,
                            'co' => $this->cur_co['Co']['id']));
    else
      parent::performRedirect();
  }
  
  /**
   * Regenerate a form after validation/save fails.
   * - postcondition: Redirect generated
   *
   * @since  COmanage Registry v0.4
   */
  
  function regenerateForm() {
    // co_people/add needs to go back to co_people/invite
    
    if($this->request->params['action'] == 'add') {
      $r['controller'] = 'co_people';
      $r['action'] = 'invite';
      if(isset($this->request->data['CoOrgIdentityLink'][0]['org_identity_id'])) {
        $r['orgidentityid'] = Sanitize::html($this->request->data['CoOrgIdentityLink'][0]['org_identity_id']);
      }
      $r['co'] = $this->cur_co['Co']['id'];
      
      $this->redirect($r);
    }
    
    return true;
  }
}