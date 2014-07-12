<?php
/**
 * Custom class VtigerModuleOperation
 *
 * Adapted and extended based on original code from vtiger CRM v.6.0.0
 *
 * Version 1.0 - 08.06.2014
 *
 * @copyright	Copyright (c) 2014, Valentin Despa. All rights reserved.
 * @author		Valentin Despa - info@vdespa.de
 * @link		http://www.vdespa.de
 */

/*+*******************************************************************************
 *  The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *
 *********************************************************************************/
require_once 'include/Webservices/VtigerActorOperation.php';

/**
 * Description of VtigerProductTaxesOperation
 */
class VtigerProductTaxesOperation extends VtigerActorOperation
{
	public function create($elementType, $element)
	{
		switch ($element['operation'])
		{
			case 'create':
			case 'update':
				unset($element['operation']);
				return $this->create_override($elementType, $element);
				break;
			case 'delete':
				unset($element['operation']);
				return $this->delete($element);
			case 'retrieve':
				unset($element['operation']);
				return $this->retrieve($element);
				break;
			default:
				$this->create_original($elementType, $element);
		}
	}

	public function create_original($elementType, $element) {
		$db = PearDatabase::getInstance();
		$sql = 'SELECT * FROM vtiger_producttaxrel WHERE productid =? AND taxid=?';
		list($typeId, $productId) = vtws_getIdComponents($element['productid']);
		list($typeId, $taxId) = vtws_getIdComponents($element['taxid']);
		$params = array($productId, $taxId);
		$result = $db->pquery($sql,$params);
		$rowCount = $db->num_rows($result);
		if($rowCount > 0) {
			$id = $db->query_result($result,0, $this->meta->getObectIndexColumn());
			$meta = $this->getMeta();
			$element['id'] = vtws_getId($meta->getEntityId(), $id);
			return $this->update($element);
		}else{
			unset($element['id']);
			return parent::create($elementType, $element);
		}
	}

	public function create_override($elementType, $element)
	{
		$db = PearDatabase::getInstance();
		$sql = 'SELECT * FROM vtiger_producttaxrel WHERE productid =? AND taxid=?';
		list($typeId, $productId) = vtws_getIdComponents($element['productid']);
		list($typeId, $taxId) = vtws_getIdComponents($element['taxid']);
		$params = array($productId, $taxId);
		$result = $db->pquery($sql,$params);
		$rowCount = $db->num_rows($result);
		if ($rowCount > 0)
		{
			$id = $db->query_result($result,0, $this->meta->getObectIndexColumn());
			$meta = $this->getMeta();
			$element['id'] = vtws_getId($meta->getEntityId(), $id);
			return $this->update($element);
		}
		else
		{
			// Check if referenced records exists
			if ($this->checkEntityForValidId($element['productid']) === true && $this->checkEntityForValidId($element['taxid']) && $element['taxpercentage'] >= 0)
			{
				$element = DataTransform::sanitizeForInsert($element, $this->meta);

				$element = $this->restrictFields($element);

				$success = $this->__create($elementType,$element);
				if(!$success){
					throw new WebServiceException(WebServiceErrorCode::$DATABASEQUERYERROR,
						vtws_getWebserviceTranslatedString('LBL_'.
							WebServiceErrorCode::$DATABASEQUERYERROR));
				}
				return $this->retrieve($element);
			}
			else
			{
				throw new WebServiceException(WebServiceErrorCode::$INVALIDID, "The provided id attribute for productid or taxid is not valid or the tax percentage is negative.");
			}
		}
	}

	public function __create($elementType,$element)
	{
		require_once 'include/utils/utils.php';

		//Insert into group vtiger_table
		$query = "INSERT INTO {$this->entityTableName}(".implode(',',array_keys($element)).
			") values(".generateQuestionMarks(array_keys($element)).")";
		$result = null;
		$transactionSuccessful = vtws_runQueryAsTransaction($query, array_values($element), $result);
		return $transactionSuccessful;
	}

	public function __delete($element)
	{
		$query = "DELETE FROM $this->entityTableName WHERE productid = ? AND taxid = ? LIMIT 1";

		$params = array(
			$element['productid'],
			$element['taxid']
		);
		$result = null;
		$transactionSuccessful = vtws_runQueryAsTransaction($query,$params,$result);
		return $transactionSuccessful;
	}

	public function delete($element)
	{
		$element = DataTransform::sanitizeForInsert($element,$this->meta);
		$element = $this->restrictFields($element);

		$success = $this->__delete($element);
		if(!$success){
			throw new WebServiceException(WebServiceErrorCode::$DATABASEQUERYERROR,
				vtws_getWebserviceTranslatedString('LBL_'.
					WebServiceErrorCode::$DATABASEQUERYERROR));
		}
		return array("status"=>"successful");
	}

	/**
	 * Check agains the database if the provided Id does really exist
	 *
	 * @param $id
	 * @return bool
	 */
	protected function checkEntityForValidId($id)
	{
		global $log,$adb, $user;

		$webserviceObject = VtigerWebserviceObject::fromId($adb, $id);
		$handlerPath = $webserviceObject->getHandlerPath();
		$handlerClass = $webserviceObject->getHandlerClass();
		require_once $handlerPath;
		$handler = new $handlerClass($webserviceObject,$user,$adb,$log);
		$meta = $handler->getMeta();
		$entityName = $meta->getObjectEntityName($id);

		if ($entityName)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function update($element)
	{
		$element = DataTransform::sanitizeForInsert($element,$this->meta);
		$element = $this->restrictFields($element);

		$success = $this->__update($element);
		if(!$success){
			throw new WebServiceException(WebServiceErrorCode::$DATABASEQUERYERROR,
				vtws_getWebserviceTranslatedString('LBL_'.
					WebServiceErrorCode::$DATABASEQUERYERROR));
		}
		return $this->retrieve($element);
	}

	public function retrieve($element)
	{
		$element = DataTransform::sanitizeForInsert($element,$this->meta);
		$element = $this->restrictFields($element);

		$success = $this->__retrieve($element);
		if(!$success){
			throw new WebServiceException(WebServiceErrorCode::$RECORDNOTFOUND,
				"Record not found");
		}
		$element = $this->getElement();

		return DataTransform::filterAndSanitize($element,$this->meta);
	}

	public function __retrieve($element)
	{
		$query = "SELECT * FROM {$this->entityTableName} WHERE productid = ? AND taxid = ?";
		$params = array(
			$element['productid'],
			$element['taxid']
		);
		$transactionSuccessful = vtws_runQueryAsTransaction($query, $params, $result);
		if(!$transactionSuccessful){
			throw new WebServiceException(WebServiceErrorCode::$DATABASEQUERYERROR,
				vtws_getWebserviceTranslatedString('LBL_'.
					WebServiceErrorCode::$DATABASEQUERYERROR));
		}
		$db = $this->pearDB;
		if($result){
			$rowCount = $db->num_rows($result);
			if($rowCount >0){
				$this->element = $db->query_result_rowdata($result,0);
				return true;
			}
		}
		return false;
	}



	public function __update($element)
	{
		$query = 'UPDATE '.$this->entityTableName . ' SET `taxpercentage`= ?' . ' WHERE productid = ? AND taxid = ?';
		$params = array(
			$element['taxpercentage'],
			$element['productid'],
			$element['taxid']
		);
		$result = null;
		$transactionSuccessful = vtws_runQueryAsTransaction($query,$params,$result);
		return $transactionSuccessful;
	}

	/**
	 * Table vtiger_producttaxrel does not implement an id, so this method will always return true
	 *
	 * @param $recordId
	 * @return bool
	 */
	function exists($recordId)
	{
		return true;
	}
}