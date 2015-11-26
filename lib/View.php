<?php

namespace Jos\Lib;

class View {

  const MODE_1 = 'Update existing pages';
  const MODE_2 = 'Delete and recreate pages';

  /**
   * @field array Default config values
   */
  protected static $fieldtypeExcludes = array(
    'FieldtypeFieldsetTabOpen', 'FieldtypeFieldsetOpen', 'FieldtypeFieldsetClose'
  );

 /**
  * construct
  */
  public function __construct() {
    $this->setData();
  }

  public function setData() {
    $this->data = wire('modules')->getModuleConfigData(\XmlParser::MODULE_NAME);
  }

  protected function getForm($isUpload = false) {
    $form = wire('modules')->get('InputfieldForm');
    $form->action = './';
    $form->method = 'post';

    if ($isUpload) $form->attr('enctype', 'multipart/form-data');

    return $form;
  }

  protected function getWrapper($title) {
    $wrapper = new \InputfieldWrapper();
    $wrapper->attr('title', $title);

    return $wrapper;
  }

  protected function getFieldset($label) {
    $set = wire('modules')->get('InputfieldFieldset');
    $set->label = $label;

    return $set;
  }

  /**
   * Add a submit button, moved to a function so we don't have to do this several times
   */
  protected function addSubmit(\InputfieldForm $form, $name = 'submit') {
    $f = wire('modules')->get('InputfieldSubmit');
    $f->name = $name;
    $f->value = __('Submit');
    $form->add($f);
  }

  protected function getField($type, $label, $name, $value, $description = '', $columnWidth = 50, $required = false) {
    $field = wire('modules')->get($type);
    $field->label = $label;
    $field->description = $description;
    $field->name = $name;
    $field->attr('value', $value);
    $field->columnWidth = $columnWidth;
    $field->required = $required;

    return $field;
  }

  protected function getAssignedFields($field) {
    $template = wire('templates')->get($this->data['xpTemplate']);
    foreach ($template->fields as $tfield) {
      $field->addOption($tfield->id, $tfield->name);
    }

    return $field;
  }

  protected function getPreconfiguration() {
    return array(
      array(
        'name' => __('Template'),
        'val' => wire('templates')->get($this->data['xpTemplate'])->name
      ),
      array(
        'name' => __('Parent'),
        'val' => wire('pages')->get($this->data['xpParent'])->title
      ),
      array(
        'name' => __('Update mode'),
        'val' => constant('self::MODE_' . $this->data['xpMode'])
      ),
      array(
        'name' => __('Path to images'),
        'val' => $this->data['xpImgPath']
      )
    );
  }

  protected function getConfiguration() {
    return json_decode($this->data['xpFields']);
  }

  public function renderMappingForm() {
    $form = $this->getForm();
    $wrapper = $this->getWrapper(__('XPATH Parser Settings'));
    $set1 = $this->getFieldset(__('XPATH Parser Settings'));
    $set2 = $this->getFieldset(__('Mapping'));

    // field context
    $fieldC = $this->getField(
      'InputfieldText',
      __('Context'),
      'xpContext',
      $this->data['xpContext'],
      __('This is the base query, all other queries will run in this context.'),
      50,
      true
    );

    // field title
    $fieldT = $this->getField(
      'InputfieldSelect',
      __('Title'),
      'xpId',
      $this->data['xpId'],
      __('Field Id is mandatory and considered unique: only one item per Title value will be created.'),
      50,
      true
    );
    $this->getAssignedFields($fieldT);

    // mapping fields
    $template = wire('templates')->get($this->data['xpTemplate']);
    $values = $this->getConfiguration();
    foreach ($template->fields as $tfield) {
      if (in_array($tfield->type->className, self::$fieldtypeExcludes)) continue; // skip some fields
      $label = $tfield->label ? $tfield->label : $tfield->name;
      $field = $this->getField('InputfieldText', $label, $tfield->name, $values->{$tfield->name});
      $field->size = 30;
      $set2->add($field);

      // case Image add description
      if ($tfield->type->className === FieldtypeImage) {
        if ($tfield->descriptionRows > 0) {
          $descName = $tfield->name . 'Description';
          $field = $this->getField(
            'InputfieldText',
            $label . ' Description',
            $descName,
            $values->$descName
          );
          $field->size = 30;
          $set2->add($field);
        }
      }
    }

    $set1->add($fieldC)->add($fieldT);
    $wrapper->add($set1)->add($set2);
    $form->add($wrapper);
    $this->addSubmit($form, 'mappingSubmit');

    return $form->render();
  }

  public function render() {
    $this->output = '<dl class="nav">';
    $this->output .= $this->renderPreconfigurationView();
    $this->output .= $this->renderConfigurationView();
    $this->output .= '</dl>';

    return $this->output;
  }

  protected function renderPreconfigurationView() {
    $edit = $this->page->url . '?action=edit-preconf';
    $this->output .= "<dt><a class='label' href='$edit'>" . __('Configuration') . "</a></dt><dd><table>";

    foreach ($this->getPreconfiguration() as $config) {
      $this->output .= "<tr><th style='padding-right: 1.5rem;'>{$config['name']}</th>";
      $this->output .= "<td>{$config['val']}</td></tr>";
    }
    $this->output .= "</table><div class='actions'><a href='$edit'>" . __('Edit') . "</a></div></dd>";
  }

  protected function renderConfigurationView() {
    $edit = $this->page->url . '?action=edit-conf';
    $fieldId = wire('fields')->get($this->data['xpId'])->name;
    $this->output .= "<dt><a class='label' href='$edit'>" . __('Mapping') . "</a></dt>";
    $this->output .= "<dd><div class='actions content'>";
    $this->output .= "<table><tr><th style='padding-right: 1.5rem;'>" . __('Context') . "</th><td>" . $this->data['xpContext'] . "</td></tr>";
    $this->output .= "<tr><th style='padding-right: 1.5rem;'>" . __('Id') . "</th><td>" . $fieldId . "</td></tr></table>";

    $this->output .= "<table><tr><th>" . __('Field') . "</th><th>" . __('Mapping') . "</th></tr>";

    foreach ($this->getConfiguration() as $field => $config) {
      if (!$config) continue;
      $this->output .= "<tr><td style='padding-right: 1.5rem;'>{$field}</td><td>{$config}</td></tr>";
    }

    $this->output .= "</table><a href='$edit'>" . __('Edit') . "</a></div></dd>";
  }

  public function renderUploadedFile() {
    $output = '';
    if ($this->data['xmlfile']) {
      $output .= '<div class="actions"><p><strong>' . __('Selected File') . ':</strong> ' . $this->data['xmlfile'];
      $output .= '<a href="' . $this->page->url . '?action=parse" style="margin-left: 10px;">' . __('Reparse file')  . '</a></p></div>';
    }

    return $output;
  }

  public function renderUploadForm() {
    $form = $this->getForm(true);
    $wrapper = $this->getWrapper(__('Upload XML'));

    $field = wire('modules')->get('InputfieldFile');
    $field->extensions = 'xml';
    $field->maxFiles = 1;
    $field->descriptionRows = 0;
    $field->overwrite = true;
    $field->attr('id+name', 'xmlfile');
    $field->label = __('XML File');
    $field->description = __('Upload a XML file.');

    $wrapper->add($field);
    $form->add($wrapper);
    $this->addSubmit($form, 'uploadSubmit');

    return $form;
  }

  public function renderPreconfigurationForm() {
    $form = $this->getForm();
    $wrapper = $this->getWrapper(__('Overview'));
    $set = $this->getFieldset(__('Settings'));

    $fieldTemplate = $this->getField(
      'InputfieldSelect',
      __('Template'),
      'xpTemplate',
      $this->data['xpTemplate'],
      '',
      50,
      true
    );

    foreach (wire('templates') as $template) {
      if ($template->flags & \Template::flagSystem) continue;
      $fieldTemplate->addOption($template->id, (!empty($template->label) ? $template->label : $template->name));
    }

    $fieldPage = $this->getField(
      'InputfieldPageListSelect',
      __('Parent Page'),
      'xpParent',
      $this->data['xpParent'],
      '',
      50,
      true
    );

    $fieldMode = $this->getField(
      'InputfieldSelect',
      __('Update Mode'),
      'xpMode',
      $this->data['xpMode'],
      __('Existing pages will be determined using mappings that are a "unique target".'),
      50,
      true
    );

    $fieldMode
      ->addOption(1, __(self::MODE_1))
      ->addOption(2, __(self::MODE_2));

    $fieldImgPath = $this->getField(
      'InputfieldText',
      __('Path to images'),
      'xpImgPath',
      $this->data['xpImgPath'],
      __('Path where the images are placed, without ending `/`.'),
      50
    );

    $set->add($fieldTemplate)->add($fieldPage)->add($fieldMode)->add($fieldImgPath);
    $wrapper->add($set);
    $form->add($wrapper);
    $this->addSubmit($form, 'preconfigSubmit');

    return $form->render();
  }

}
