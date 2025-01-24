<?php
/**
 * @copyright Copyright (c) 2016 by 1-more-thing (http://1-more-thing.com) All rights reserved.
 * @license BSD
 */
namespace airmoi\FileMaker\Object;

use airmoi\FileMaker\FileMaker;
use airmoi\FileMaker\FileMakerException;
use airmoi\FileMaker\Parser\FMPXMLLAYOUT;

/**
 * Layout description class. Contains all the information about a
 * specific layout. Can be requested directly or returned as part of
 * a result set.
 *
 * @package FileMaker
 */
class Layout
{
    /**
     *
     * @var FileMaker
     */
    public $fm;
    public $name;
    /**  @var Field[] */
    public $fields = [];
    public $relatedSets = [];
    public $valueLists = [];
    public $valueListTwoFields = [];
    public $database;
    public $extended = false;
    public $table = false;
    /**
     * Layout object constructor.
     *
     * @param FileMaker $fm FileMaker object
     *        that this layout was created through.
     */
    public function __construct(FileMaker $fm)
    {
        $this->fm = $fm;
    }

    /**
     * Returns the name of this layout.
     *
     * @return string Layout name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the name of the database that this layout is in.
     *
     * @return string Database name.
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Returns an array with the names of all fields in this layout.
     *
     * @return array List of field names as strings.
     */
    public function listFields()
    {
        return array_keys($this->fields);
    }

    /**
     * Returns a Field object that describes the specified field.
     *
     * @param string $fieldName Name of field.
     *
     * @return Field|FileMakerException Field object, if successful.
     * @throws FileMakerException
     */
    public function getField($fieldName)
    {
        if (isset($this->fields[$fieldName])) {
            return $this->fields[$fieldName];
        }
        if ($pos = strpos($fieldName, ':')) {
            $relatedSet = substr($fieldName, 0, $pos);
            //$fieldName = substr($fieldName, $pos+1, strlen($fieldName));
            $result = $this->getRelatedSet($relatedSet);
            if (FileMaker::isError($result)) {
                return $result;
            }
            return $result->getField($fieldName);
        }
        return $this->fm->returnOrThrowException('Field "'.$fieldName.'" Not Found');
    }

    /**
     * Returns an associative array with the names of all fields as
     * keys and Field objects as the array values.
     *
     * @return Field[] an array of Field objects.
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Returns an array of related table names for all portals on
     * this layout.
     *
     * @return array List of related table names as strings.
     */
    public function listRelatedSets()
    {
        return array_keys($this->relatedSets);
    }

    /**
     * Returns a RelatedSet object that describes the specified
     * portal.
     *
     * @param string|FileMakerException $relatedSet Name of the related table for a portal.
     * @throws FileMakerException
     *
     * @return RelatedSet|FileMakerException a RelatedSet object
     */
    public function getRelatedSet($relatedSet)
    {
        if (isset($this->relatedSets[$relatedSet])) {
            return $this->relatedSets[$relatedSet];
        }
        return $this->fm->returnOrThrowException('RelatedSet "'.$relatedSet.'" Not Found in layout '. $this->getName());
    }

    /**
     * Check wether a portal based on the given table occurrence exists
     * @param string $relatedSet Table occurrence name to test
     * @return bool true if related set exist
     */
    public function hasRelatedSet($relatedSet)
    {
        return isset($this->relatedSets[$relatedSet]);
    }

    /**
     * Returns an associative array with the related table names of all
     * portals as keys and RelatedSet objects as the array values.
     *
     * @return RelatedSet[] Array of {@link RelatedSet} objects.
     */
    public function getRelatedSets()
    {
        return $this->relatedSets;
    }

    /**
     * Returns the names of any value lists associated with this
     * layout.
     *
     * @return array|FileMakerException List of value list names as strings.
     * @throws FileMakerException
     */
    public function listValueLists()
    {
        $extendedInfos = $this->loadExtendedInfo();
        if (FileMaker::isError($extendedInfos)) {
            return $extendedInfos;
        }
        if ($this->valueLists !== null) {
            return array_keys($this->valueLists);
        }

        return [];
    }

    /**
     * Returns the list of defined values in the specified value list.
     *
     * @param string $listName Name of value list.
     * @param string $recid Record from which the value list should be
     *        displayed.
     *
     * @return array|FileMakerException List of defined values.

     * @throws FileMakerException
     * @deprecated Use getValueListTwoFields instead.

     * @see getValueListTwoFields
     */
    public function getValueList($listName, $recid = null)
    {
        $extendedInfos = $this->loadExtendedInfo($recid);
        if (FileMaker::isError($extendedInfos)) {
            return $extendedInfos;
        }
        return isset($this->valueLists[$listName]) ?
                $this->valueLists[$listName] : null;
    }



    /**
     * Returns the list of defined values in the specified value list.
     * This method supports single, 2nd only, and both fields value lists.
     *
     * @param string $valueList Name of value list.
     * @param string  $recid Record from which the value list should be
     *        displayed.
     *
     * @return array|FileMakerException of display names and its corresponding value from the value list
     * @throws FileMakerException
     */
    public function getValueListTwoFields($valueList, $recid = null)
    {

        $extendedInfos = $this->loadExtendedInfo($recid);
        if (FileMaker::isError($extendedInfos)) {
            return $extendedInfos;
        }
        return isset($this->valueLists[$valueList]) ?
                $this->valueListTwoFields[$valueList] : [];
    }

    /**
     * Returns a multi-level associative array of value lists.
     * The top-level array has names of value lists as keys and arrays as
     * values. The second level arrays are the lists of defined values from
     * each value list.
     *
     * @param string  $recid Record from which the value list should be
     *        displayed.
     *
     * @return array|FileMakerException Array of value-list arrays.
     * @throws FileMakerException
     * @deprecated Use getValueListTwoFields instead.
     * @see getValueListsTwoFields
     */
    public function getValueLists($recid = null)
    {
        $extendedInfos = $this->loadExtendedInfo($recid);
        if (FileMaker::isError($extendedInfos)) {
            return $extendedInfos;
        }
        return $this->valueLists;
    }

    /**
     * Returns a multi-level associative array of value lists.
     * The top-level array has names of value lists as keys and associative arrays as
     * values. The second level associative arrays are lists of display name and its corresponding
     * value from the value list.
     *
     * @param string|FileMakerException $recid Record from which the value list should be
     *        displayed.
     * @throws FileMakerException
     *
     * @return array|FileMakerException Array of value-list associative arrays.
     */
    public function getValueListsTwoFields($recid = null)
    {
        $extendedInfos = $this->loadExtendedInfo($recid);
        if (FileMaker::isError($extendedInfos)) {
            return $extendedInfos;
        }
        return $this->valueListTwoFields;
    }

    /**
     * Loads extended (FMPXMLLAYOUT) layout information.
     *
     * @access private
     *
     * @param string  $recid Record from which to load extended information.
     *
     * @return boolean|FileMakerException TRUE, if successful.
     * @throws FileMakerException
     */
    public function loadExtendedInfo($recid = null)
    {
        if (!$this->extended || $recid != null) {
            if ($recid != null) {
                $result = $this->fm->execute([
                    '-db' => $this->fm->getProperty('database'),
                    '-lay' => $this->getName(),
                    '-recid' => $recid,
                    '-view' => null
                ], 'FMPXMLLAYOUT');
            } else {
                $result = $this->fm->execute([
                    '-db' => $this->fm->getProperty('database'),
                    '-lay' => $this->getName(),
                    '-view' => null
                ], 'FMPXMLLAYOUT');
            }
            $parser = new FMPXMLLAYOUT($this->fm);
            $parseResult = $parser->parse($result);
            if (FileMaker::isError($parseResult)) {
                return $parseResult;
            }

            $parser->setExtendedInfo($this);
            $this->extended = true;

            if ($recid === null){
                $this->fm->cacheSet($this->getName(), $this);
            }
        }
        return $this->extended;
    }
}
