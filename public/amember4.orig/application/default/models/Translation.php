<?php
/**
 * Class represents records from table translations
 * {autogenerated}
 * @property string $language 
 * @property string $translations 
 * @see Am_Table
 */
class Translation extends Am_Record {
    protected $_key = 'trans_id';
    protected $_table = '?_translation';
}

class TranslationTable extends Am_Table 
{
    protected $_key = 'trans_id';
    protected $_table = '?_translation';
    protected $_recordClass = 'Translation';

    function getTranslationData($language) 
    {
        $translations = $this->_db->selectCell("SELECT translations FROM ?_translation WHERE language = ?", $language);
        return $translations ? unserialize($translations) : array();
    }

    function replaceTranslation($translations, $language)
    {
        $prevTranslations = (array)$this->getTranslationData($language);
        $newTranslations = array_merge($prevTranslations, $translations);
        $sql = "REPLACE ?_translation SET translations = ?, language = ?";
        $this->_db->query($sql, serialize($newTranslations), $language);
    }
}
