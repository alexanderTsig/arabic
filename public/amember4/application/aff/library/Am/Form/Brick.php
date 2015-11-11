<?php

class Am_Form_Brick_Payout extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_DONT;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Payout Method');
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $module = Am_Di::getInstance()->modules->loadGet('aff');
        if ($module->getConfig('payout_methods'))
            Am_Di::getInstance()->modules->loadGet('aff')->addPayoutInputs($form);
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup_Aff;
    }
}
