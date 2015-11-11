<?php

class Am_Theme_Sample extends Am_Theme_Default
{
    protected $publicWithVars = array(
        'css/theme.css',
    );
    public function initSetupForm(Am_Form_Setup_Theme $form)
    {
        parent::initSetupForm($form);
        $form->addText('bgcolor')->setLabel('Background Color')->default = '#00e';
    }
}