<?php

class AdminEmailTemplateLayoutController extends Am_Controller_Grid
{
    const DEFAULT_LAYOUT_THRESHOLD = 100;
    
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_SETUP);
    }

    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->emailTemplateLayoutTable);
        $grid = new Am_Grid_Editable('_etl', ___('Email Template Layouts'), $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('name', ___('Title')))
            ->setRenderFunction(array($this, 'renderTitle'));
        $grid->setForm(array($this, 'createForm'));
        $grid->setRecordTitle(___('Layout'));
        $grid->actionGet('delete')
            ->setIsAvailableCallback(function($r) {return $r->pk()>3;});

        return $grid;
    }

    public function createForm($grid) {
        $form = new Am_Form_Admin();

        $name = $form->addText('name', array('class' => 'el-wide'))
            ->setLabel(___('Title'));

        $r = $grid->getRecord();
        if ($r->isLoaded() && $r->pk() < self::DEFAULT_LAYOUT_THRESHOLD) {
            $name->toggleFrozen('true');
        } else {
            $name->addRule('required');
        }

        $form->addTextarea('layout', array('rows' => 25, 'class' => 'row-wide el-wide'))
            ->setLabel(___("Layout\n" .
                "use placholder %content% for email output"))
            ->addRule('callback', ___('Your layout has not %content% placeholder'), array($this, 'checkLayout'));
        return $form;
    }

    public function renderTitle($r)
    {
        $tpl = $r->pk() > self::DEFAULT_LAYOUT_THRESHOLD ? '<td>%s</td>' : '<td><strong>%s</strong></td>';
        return sprintf($tpl, $this->escape($r->name));
    }

    public function checkLayout($c)
    {
        return !(strpos($c, '%content%') === false);
    }
}