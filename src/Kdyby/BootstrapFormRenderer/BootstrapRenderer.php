<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\BootstrapFormRenderer;

use Nette;
use Nette\Forms\Controls;
use Nette\Iterators\Filter;
use Nette\Bridges\FormsLatte\FormMacros;
use Nette\Templating\FileTemplate;
use Nette\Utils\Html;


if (!class_exists('Nette\Bridges\FormsLatte\FormMacros')) {
	class_alias('Nette\Latte\Macros\FormMacros', 'Nette\Bridges\FormsLatte\FormMacros');
}


/**
 * Created with twitter bootstrap in mind.
 *
 * <code>
 * $form->setRenderer(new Kdyby\BootstrapFormRenderer\BootstrapRenderer);
 * </code>
 *
 * @author Pavel Ptacek
 * @author Filip Procházka
 */
class BootstrapRenderer extends Nette\Object implements Nette\Forms\IFormRenderer
{

	public static $checkboxListClasses = array(
		'Nextras\Forms\Controls\MultiOptionList',
		'Nette\Forms\Controls\CheckboxList',
		'Kdyby\Forms\Controls\CheckboxList',
	);

	/**
	 * set to false, if you want to display the field errors also as form errors
	 * @var bool
	 */
	public $errorsAtInputs = TRUE;

	/**
	 * Groups that should be rendered first
	 */
	public $priorGroups = array();

	/**
	 * @var \Nette\Forms\Form
	 */
	private $form;

	/**
	 * @var \Nette\Templating\Template|\stdClass
	 */
	private $template;



	/**
	 * @param \Nette\Templating\FileTemplate $template
	 */
	public function __construct(FileTemplate $template = NULL)
	{
		$this->template = $template;
	}



	/**
	 * Render the templates
	 *
	 * @param \Nette\Forms\Form $form
	 * @param string $mode
	 * @param array $args
	 * @return void
	 */
	public function render(Nette\Forms\Form $form, $mode = NULL, $args = NULL)
	{
		if ($this->template === NULL) {
			if ($presenter = $form->lookup('Nette\Application\UI\Presenter', FALSE)) {
				/** @var \Nette\Application\UI\Presenter $presenter */
				$this->template = clone $presenter->getTemplate();

			} else {
				$this->template = new FileTemplate();
				$this->template->registerFilter(new Nette\Latte\Engine());
			}
		}

		if ($this->form !== $form) {
			$this->form = $form;

			// translators
			if ($translator = $this->form->getTranslator()) {
				$this->template->setTranslator($translator);
			}

			// controls placeholders & classes
			foreach ($this->form->getControls() as $control) {
				$this->prepareControl($control);
			}

			$formEl = $form->getElementPrototype();
			if (!($classes = self::getClasses($formEl)) || stripos($classes, 'form-') === FALSE) {
				$formEl->addClass('form-horizontal');
			}

		} elseif ($mode === 'begin') {
			foreach ($this->form->getControls() as $control) {
				/** @var \Nette\Forms\Controls\BaseControl $control */
				$control->setOption('rendered', FALSE);
			}
		}

		$this->template->setFile(__DIR__ . '/@form.latte');
		$this->template->setParameters(
			array_fill_keys(array('control', '_control', 'presenter', '_presenter'), NULL) +
			array('_form' => $this->form, 'form' => $this->form, 'renderer' => $this)
		);

		if ($mode === NULL) {
			if ($args) {
				$this->form->getElementPrototype()->addAttributes($args);
			}
			$this->template->render();

		} elseif ($mode === 'begin') {
			FormMacros::renderFormBegin($this->form, (array)$args);

		} elseif ($mode === 'end') {
			FormMacros::renderFormEnd($this->form);

		} else {

			$attrs = array('input' => array(), 'label' => array());
			foreach ((array) $args as $key => $val) {
				if (stripos($key, 'input-') === 0) {
					$attrs['input'][substr($key, 6)] = $val;

				} elseif (stripos($key, 'label-') === 0) {
					$attrs['label'][substr($key, 6)] = $val;
				}
			}

			$this->template->setFile(__DIR__ . '/@parts.latte');
			$this->template->mode = $mode;
			$this->template->attrs = (array) $attrs;
			$this->template->render();
		}
	}



	/**
	 * @param \Nette\Forms\Controls\BaseControl $control
	 */
	private function prepareControl(Controls\BaseControl $control)
	{
		$translator = $this->form->getTranslator();
		$control->setOption('rendered', FALSE);

		if ($control->isRequired()) {
			$control->getLabelPrototype()->addClass('required');
			$control->setOption('required', TRUE);
		}

		$el = $control->getControlPrototype();
    if ($control instanceof Controls\TextInput) {
      $el->addClass('form-control');
    }
		
		if ($placeholder = $control->getOption('placeholder')) {
			if (!$placeholder instanceof Html && $translator) {
				$placeholder = $translator->translate($placeholder);
			}
			$el->placeholder($placeholder);
		}

		if ($control->controlPrototype->type === 'email'
			&& $control->getOption('input-prepend') === NULL
		) {
			$control->setOption('input-prepend', '@');
		}

		if ($control instanceof Nette\Forms\ISubmitterControl) {
			$el->addClass('btn');

		} else {
			$label = $control->labelPrototype;
			if ($control instanceof Controls\Checkbox) {
				$label->addClass('checkbox');

			} elseif (!$control instanceof Controls\RadioList && !self::isCheckboxList($control)) {
				$label->addClass('control-label');
			}

			$control->setOption('pairContainer', $pair = Html::el('div'));
			$pair->id = $control->htmlId . '-pair';
			$pair->addClass('control-group');
			if ($control->getOption('required', FALSE)) {
				$pair->addClass('required');
			}
			if ($control->errors) {
				$pair->addClass('error');
			}

			if ($prepend = $control->getOption('input-prepend')) {
				$prepend = Html::el('span', array('class' => 'add-on'))
					->{$prepend instanceof Html ? 'add' : 'setText'}($prepend);
				$control->setOption('input-prepend', $prepend);
			}

			if ($append = $control->getOption('input-append')) {
				$append = Html::el('span', array('class' => 'add-on'))
					->{$append instanceof Html ? 'add' : 'setText'}($append);
				$control->setOption('input-append', $append);
			}
		}
	}



	/**
	 * @return array
	 */
	public function findErrors()
	{
		$formErrors = $this->form->getErrors();

		if (!$formErrors) {
			return array();
		}

		$form = $this->form;
		$translate = function ($errors) use ($form) {
			if ($translator = $form->getTranslator()) { // If we have translator, translate!
				foreach ($errors as $key => $val) {
					$errors[$key] = $translator->translate($val);
				}
			}

			return $errors;
		};

		if (!$this->errorsAtInputs) {
			return $translate($formErrors);
		}

		return $translate($this->form->getErrors());
	}



	/**
	 * @throws \RuntimeException
	 * @return object[]
	 */
	public function findGroups()
	{
		$formGroups = $visitedGroups = array();
		foreach ($this->priorGroups as $i => $group) {
			if (!$group instanceof Nette\Forms\ControlGroup) {
				if (!$group = $this->form->getGroup($group)) {
					$groupName = (string)$this->priorGroups[$i];
					throw new \RuntimeException("Form has no group $groupName.");
				}
			}

			$visitedGroups[] = $group;
			if ($group = $this->processGroup($group)) {
				$formGroups[] = $group;
			}
		}

		foreach ($this->form->groups as $group) {
			if (!in_array($group, $visitedGroups, TRUE) && ($group = $this->processGroup($group))) {
				$formGroups[] = $group;
			}
		}

		return $formGroups;
	}



	/**
	 * @param \Nette\Forms\Container $container
	 * @param boolean $buttons
	 * @return \Iterator
	 */
	public function findControls(Nette\Forms\Container $container = NULL, $buttons = NULL)
	{
		$container = $container ? : $this->form;
		return new Filter($container->getControls(), function ($control) use ($buttons) {
			$control = $control instanceof Filter ? $control->current() : $control;
			$isButton = $control instanceof Controls\Button || $control instanceof Nette\Forms\ISubmitterControl;
			return !$control->getOption('rendered')
				&& !$control instanceof Controls\HiddenField
				&& (($buttons === TRUE && $isButton) || ($buttons === FALSE && !$isButton) || $buttons === NULL);
		});
	}



	/**
	 * @internal
	 * @param \Nette\Forms\ControlGroup $group
	 * @return object
	 */
	public function processGroup(Nette\Forms\ControlGroup $group)
	{
		if (!$group->getOption('visual') || !$group->getControls()) {
			return NULL;
		}

		$groupLabel = $group->getOption('label');
		$groupDescription = $group->getOption('description');

		// If we have translator, translate!
		if ($translator = $this->form->getTranslator()) {
			if (!$groupLabel instanceof Html) {
				$groupLabel = $translator->translate($groupLabel);
			}
			if (!$groupDescription instanceof Html) {
				$groupDescription = $translator->translate($groupDescription);
			}
		}

		$controls = array_filter($group->getControls(), function (Controls\BaseControl $control) {
			return !$control->getOption('rendered')
				&& !$control instanceof Controls\HiddenField;
		});

		if (!$controls) {
			return NULL; // do not render empty groups
		}

		$groupAttrs = $group->getOption('container', Html::el())->setName(NULL);
		/** @var Html $groupAttrs */
		$groupAttrs->attrs += array_diff_key($group->getOptions(), array_fill_keys(array(
			'container', 'label', 'description', 'visual' // these are not attributes
		), NULL));

		// fake group
		return (object)(array(
			'controls' => $controls,
			'label' => $groupLabel,
			'description' => $groupDescription,
			'attrs' => $groupAttrs,
		) + $group->getOptions());
	}



	/**
	 * @internal
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @return string
	 */
	public static function getControlName(Controls\BaseControl $control)
	{
		return $control->lookupPath('Nette\Forms\Form');
	}



	/**
	 * @internal
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @return \Nette\Utils\Html
	 */
	public static function getControlDescription(Controls\BaseControl $control)
	{
		if (!$desc = $control->getOption('description')) {
			return Html::el();
		}

		// If we have translator, translate!
		if (!$desc instanceof Html && ($translator = $control->form->getTranslator())) {
			$desc = $translator->translate($desc); // wtf?
		}

		// create element
		return Html::el('p', array('class' => 'help-block'))
			->{$desc instanceof Html ? 'add' : 'setText'}($desc);
	}



	/**
	 * @internal
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @return \Nette\Utils\Html
	 */
	public function getControlError(Controls\BaseControl $control)
	{
		if (!($errors = $control->getErrors()) || !$this->errorsAtInputs) {
			return Html::el();
		}
		$error = reset($errors);

		// If we have translator, translate!
		if (!$error instanceof Html && ($translator = $control->form->getTranslator())) {
			$error = $translator->translate($error); // wtf?
		}

		// create element
		return Html::el('p', array('class' => 'help-inline'))
			->{$error instanceof Html ? 'add' : 'setText'}($error);
	}



	/**
	 * @internal
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @return string
	 */
	public static function getControlTemplate(Controls\BaseControl $control)
	{
		return $control->getOption('template');
	}



	/**
	 * @internal
	 * @param \Nette\Forms\IControl $control
	 * @return bool
	 */
	public static function isButton(Nette\Forms\IControl $control)
	{
		return $control instanceof Controls\Button;
	}



	/**
	 * @internal
	 * @param \Nette\Forms\IControl $control
	 * @return bool
	 */
	public static function isSubmitButton(Nette\Forms\IControl $control = NULL)
	{
		return $control instanceof Nette\Forms\ISubmitterControl;
	}



	/**
	 * @internal
	 * @param \Nette\Forms\IControl $control
	 * @return bool
	 */
	public static function isCheckbox(Nette\Forms\IControl $control)
	{
		return $control instanceof Controls\Checkbox;
	}



	/**
	 * @internal
	 * @param \Nette\Forms\IControl $control
	 * @return bool
	 */
	public static function isRadioList(Nette\Forms\IControl $control)
	{
		return $control instanceof Controls\RadioList;
	}



	/**
	 * @internal
	 * @param \Nette\Forms\IControl $control
	 * @return bool
	 */
	public static function isCheckboxList(Nette\Forms\IControl $control)
	{
		foreach (static::$checkboxListClasses as $class) {
			if (class_exists($class, FALSE) && $control instanceof $class) {
				return TRUE;
			}
		}

		return FALSE;
	}



	/**
	 * @internal
	 * @param \Nette\Forms\Controls\RadioList $control
	 * @return bool
	 */
	public static function getRadioListItems(Controls\RadioList $control)
	{
		$items = array();
		foreach ($control->items as $key => $value) {
			$el = $control->getControlPart($key);
			if ($el->getName() === 'input') {
				$items[$key] = $radio = (object) array(
					'input' => $el,
					'label' => $cap = $control->getLabelPart($key),
					'caption' => $cap->getText(),
				);

			} else {
				$items[$key] = $radio = (object) array(
					'input' => $el[0],
					'label' => $el[1],
					'caption' => $el[1]->getText(),
				);
			}

			$radio->label->addClass('radio');
			$radio->html = clone $radio->label;
			$radio->html->insert(0, $radio->input);
		}

		return $items;
	}



	/**
	 * @internal
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @throws \Nette\InvalidArgumentException
	 * @return bool
	 */
	public static function getCheckboxListItems(Controls\BaseControl $control)
	{
		$items = array();
		foreach ($control->items as $key => $value) {
			if (method_exists($control, 'getControlPart')) {
				$el = $control->getControlPart($key);
				$items[$key] = $check = (object) array(
					'input'   => $el,
					'label'   => $cap = $control->getLabelPart($key),
					'caption' => $cap->getText(),
				);
			} else {
				$el = $control->getControl($key);
				if (is_string($el)) {
					$items[$key] = $check = (object) array(
						'input'   => Html::el()->setHtml($el),
						'label'   => Html::el(),
						'caption' => $value,
					);
				} else {
					$items[$key] = $check = (object) array(
						'input'   => $el[0],
						'label'   => $el[1],
						'caption' => $el[1]->getText(),
					);
				}
			}
			$check->html = clone $check->label;
			$check->html->addClass('checkbox');
			$display = $control->getOption('display', 'inline');
			if ($display == 'inline') {
				$check->html->addClass($display);
			}
			$check->html->insert(0, $check->input);
		}

		return $items;
	}



	/**
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @return \Nette\Utils\Html
	 */
	public static function getLabelBody(Controls\BaseControl $control)
	{
		$label = $control->getLabel();
		return $label;
	}



	/**
	 * @param \Nette\Forms\Controls\BaseControl $control
	 * @param string $class
	 * @return bool
	 */
	public static function controlHasClass(Controls\BaseControl $control, $class)
	{
		$classes = explode(' ', self::getClasses($control->controlPrototype));
		return in_array($class, $classes, TRUE);
	}



	/**
	 * @param \Nette\Utils\Html $_this
	 * @param array $attrs
	 * @return \Nette\Utils\Html
	 */
	public static function mergeAttrs(Html $_this = NULL, array $attrs)
	{
		if ($_this === NULL) {
			return Html::el();
		}

		$_this->attrs = array_merge_recursive($_this->attrs, $attrs);
		return $_this;
	}



	/**
	 * @param \Nette\Utils\Html $el
	 * @return bool
	 */
	private static function getClasses(Html $el)
	{
		if (is_array($el->class)) {
			$classes = array_filter(array_merge(array_keys($el->class), $el->class), 'is_string');
			return implode(' ', $classes);
		}
		return $el->class;
	}

}
