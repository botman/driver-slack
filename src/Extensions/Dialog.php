<?php

namespace BotMan\Drivers\Slack\Extensions;

use Illuminate\Support\Collection;

abstract class Dialog
{
    /** @var string */
    protected $title;

    /** @var string */
    protected $submitLabel;

    /** @var string */
    protected $callbackId;

    /** @var array */
    protected $elements = [];

    /**
     * Validate the submitted form data.
     *
     * @param Collection $submission
     * @return array
     */
    public function errors(Collection $submission)
    {
        return [];
    }

    /**
     * Build your form.
     *
     * @return void
     */
    abstract public function buildForm();

    /**
     * @param string $label
     * @param string $name
     * @param string $type
     * @param array $additional
     * @return $this
     */
    public function add(string $label, string $name, string $type, array $additional = [])
    {
        $this->elements[] = array_merge([
            'type' => $type,
            'name' => $name,
            'label' => $label,
        ], $additional);

        return $this;
    }

    /**
     * @param string $label
     * @param string $name
     * @param null $value
     * @param array $additional
     * @return Dialog
     */
    public function text(string $label, string $name, $value = null, $additional = [])
    {
        return $this->add($label, $name, 'text', array_merge([
            'value' => $value,
        ], $additional));
    }

    /**
     * @param string $label
     * @param string $name
     * @param null $value
     * @param array $additional
     * @return Dialog
     */
    public function textarea(string $label, string $name, $value = null, $additional = [])
    {
        return $this->add($label, $name, 'textarea', array_merge([
            'value' => $value,
        ], $additional));
    }

    /**
     * @param string $label
     * @param string $name
     * @param null $value
     * @param array $additional
     * @return Dialog
     */
    public function email(string $label, string $name, $value = null, $additional = [])
    {
        return $this->add($label, $name, 'text', array_merge([
            'subtype' => 'email',
            'value' => $value,
        ], $additional));
    }

    /**
     * @param string $label
     * @param string $name
     * @param null $value
     * @param array $additional
     * @return Dialog
     */
    public function number(string $label, string $name, $value = null, $additional = [])
    {
        return $this->add($label, $name, 'text', array_merge([
            'subtype' => 'number',
            'value' => $value,
        ], $additional));
    }

    /**
     * @param string $label
     * @param string $name
     * @param null $value
     * @param array $additional
     * @return Dialog
     */
    public function tel(string $label, string $name, $value = null, $additional = [])
    {
        return $this->add($label, $name, 'text', array_merge([
            'subtype' => 'tel',
            'value' => $value,
        ], $additional));
    }

    /**
     * @param string $label
     * @param string $name
     * @param null $value
     * @param array $additional
     * @return Dialog
     */
    public function url(string $label, string $name, $value = null, $additional = [])
    {
        return $this->add($label, $name, 'text', array_merge([
            'subtype' => 'url',
            'value' => $value,
        ], $additional));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $this->buildForm();

        return [
            'callback_id' => $this->callbackId,
            'title' => $this->title,
            'submit_label' => $this->submitLabel,
            'elements' => $this->elements,
        ];
    }
}
