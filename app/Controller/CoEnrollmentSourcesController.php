<?php
/**
 * COmanage Registry CO Enrollment Attributes Controller
 *
 * Copyright (C) 2016 SCG
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
 * @copyright     Copyright (C) 2016 SCG
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v1.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 * @version       $Id$
 */

App::uses("StandardController", "Controller");
  
class CoEnrollmentSourcesController extends StandardController {
  // Class name, used by Cake
  public $name = "CoEnrollmentSources";
  
  // Use the javascript helper for the Views (for drag/drop in particular)
  public $helpers = array('Js');

  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'CoEnrollmentSource.ordr' => 'asc'
    )
  );
  
  // We don't directly require a CO, but indirectly we do.
  public $requires_co = true;

  /**
   * Callback before views are rendered.
   *
   * @since  COmanage Registry v1.1.0
   */
  
  function beforeRender() {
    parent::beforeRender();
    
    if(!$this->request->is('restful')) {
      // Figure out our enrollment flow ID
      
      $coefid = null;
      
      if($this->action == 'add' || $this->action == 'index' || $this->action == 'order') {
        // Accept coefid from the url or the form
        
        if(!empty($this->request->params['named']['coef'])) {
          $coefid = Sanitize::html($this->request->params['named']['coef']);
        } elseif(!empty($this->request->data['CoEnrollmentSource']['co_enrollment_flow_id'])) {
          $coefid = $this->request->data['CoEnrollmentSource']['co_enrollment_flow_id'];
        }
      } elseif(!empty($this->request->params['pass'][0])) {
        // Map the enrollment flow from the requested object
        
        $coefid = $this->CoEnrollmentSource->field('co_enrollment_flow_id',
                                                   array('id' => $this->request->params['pass'][0]));
      }
      
      $efname = $this->CoEnrollmentSource->CoEnrollmentFlow->field('name', array('CoEnrollmentFlow.id' => $coefid));
      
      // Override page title
      $this->set('title_for_layout', $this->viewVars['title_for_layout'] . " (" . $efname . ")");
      $this->set('vv_ef_name', $efname);
      $this->set('vv_ef_id', $coefid);
      
      // We need to pull a list of available Org Identity Sources, but we also
      // need to determine what capabilities they support.
      
      $args = array();
      $args['conditions']['OrgIdentitySource.co_id'] = $this->cur_co['Co']['id'];
      $args['conditions']['OrgIdentitySource.status'] = SuspendableStatusEnum::Active;
      $args['fields'] = array('id', 'description');
      $args['order'] = 'description';
      $args['contain'] = false;
      
      $ois = $this->CoEnrollmentSource->OrgIdentitySource->find('list', $args);
      $this->set('vv_avail_ois', $ois);
    }
  }
  
  /**
   * Determine the CO ID based on some attribute of the request.
   * This method is intended to be overridden by model-specific controllers.
   *
   * @since  COmanage Registry v1.1.0
   * @return Integer CO ID, or null if not implemented or not applicable.
   * @throws InvalidArgumentException
   */
  
  protected function calculateImpliedCoId($data = null) {
    // If an enrollment flow is specified, use it to get to the CO ID
    
    $coef = null;
    
    if(!empty($this->params->named['coef'])) {
      $coef = $this->params->named['coef'];
    } elseif(!empty($this->request->data['CoEnrollmentSource']['co_enrollment_flow_id'])) {
      $coef = $this->request->data['CoEnrollmentSource']['co_enrollment_flow_id'];
    }
    
    if($coef) {
      // Map CO Enrollment Flow to CO
      
      $coId = $this->CoEnrollmentSource->CoEnrollmentFlow->field('co_id',
                                                                 array('id' => $coef));
      
      if($coId) {
        return $coId;
      } else {
        throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.co_enrollment_flows.1'), $coef)));
      }
    }
    
    // Or try the default behavior
    return parent::calculateImpliedCoId();
  }
  
  /**
   * Perform any dependency checks required prior to a write (add/edit) operation.
   *
   * @since  COmanage Registry v1.1.0
   * @param  Array Request data
   * @param  Array Current data
   * @return boolean true if dependency checks succeed, false otherwise.
   */

  function checkWriteDependencies($reqdata, $curdata = null) {
    // On an add, make sure there is not already a Source using the requested OIS configuration
    // in the requested mode. (We don't do this in beforeSave because we don't currently try/catch
    // save(), so we can't pass an error message up the stack in a graceful way.)
    
    $args = array();
    $args['conditions']['CoEnrollmentSource.co_enrollment_flow_id'] = $reqdata['CoEnrollmentSource']['co_enrollment_flow_id'];
    $args['conditions']['CoEnrollmentSource.org_identity_source_id'] = $reqdata['CoEnrollmentSource']['org_identity_source_id'];
    $args['conditions']['CoEnrollmentSource.org_identity_mode'] = $reqdata['CoEnrollmentSource']['org_identity_mode'];
    $args['contain'] = false;
    
    $es = $this->CoEnrollmentSource->find('all', $args);
    
    if(!empty($es)) {
      $this->Flash->set(_txt('er.es.exists'), array('key' => 'error'));
      return false;
    }
    
    return true;      
  }
  
  /**
   * Authorization for this Controller, called by Auth component
   * - precondition: Session.Auth holds data used for authz decisions
   * - postcondition: $permissions set with calculated permissions
   *
   * @since  COmanage Registry v1.1.0
   * @return Array Permissions
   */
  
  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();
    
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    
    // Determine what operations this user can perform
    
    // Add a new CO Enrollment Source?
    $p['add'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // Delete an existing CO Enrollment Source?
    $p['delete'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // Edit an existing CO Enrollment Source?
    $p['edit'] = ($roles['cmadmin'] || $roles['coadmin']);

    // Edit an existing CO Enrollment Source's order?
    $p['order'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // View all existing CO Enrollment Source?
    $p['index'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // Modify ordering for display via AJAX 
    $p['reorder'] = ($roles['cmadmin'] || $roles['coadmin']);

    // View an existing CO Enrollment Source?
    $p['view'] = ($roles['cmadmin'] || $roles['coadmin']);

    $this->set('permissions', $p);
    return $p[$this->action];
  }

  /**
   * Determine the conditions for pagination of the index view, when rendered via the UI.
   *
   * @since  COmanage Registry v1.1.0
   * @return Array An array suitable for use in $this->paginate
   */
  
  function paginationConditions() {
    // Only retrieve attributes in the current enrollment flow
    
    $ret = array();
    
    $ret['conditions']['CoEnrollmentSource.co_enrollment_flow_id'] = $this->request->params['named']['coef'];
    
    return $ret;
  }
  
  /**
   * Perform a redirect back to the controller's default view.
   * - postcondition: Redirect generated
   *
   * @since  COmanage Registry v1.1.0
   */
  
  function performRedirect() {
    // Append the enrollment flow ID to the redirect
    
    if(isset($this->request->data['CoEnrollmentSource']['co_enrollment_flow_id']))
      $coefid = $this->request->data['CoEnrollmentSource']['co_enrollment_flow_id'];
    elseif(isset($this->request->params['named']['coef']))
      $coefid = Sanitize::html($this->request->params['named']['coef']);
    
    $this->redirect(array('controller' => 'co_enrollment_sources',
                          'action' => 'index',
                          'coef' => $coefid));
  }
}