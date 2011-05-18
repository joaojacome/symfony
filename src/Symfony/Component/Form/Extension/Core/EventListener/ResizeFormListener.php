<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ResizeFormListener implements EventSubscriberInterface
{
    /**
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * @var string
     */
    private $type;

    /**
     * Whether children could be added to the group
     * @var Boolean
     */
    private $allowAdd;

    /**
     * Whether children could be removed from the group
     * @var Boolean
     */
    private $allowDelete;

    /**
     * @var array
     */
    private $typeOptions;

    public function __construct(FormFactoryInterface $factory, $type, $allowAdd = false, $allowDelete = false, array $typeOptions = array())
    {
        $this->factory = $factory;
        $this->type = $type;
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->typeOptions = $typeOptions;
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::preSetData,
            Events::preBind,
            Events::onBindNormData,
        );
    }

    public function preSetData(DataEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !$data instanceof \Traversable) {
            throw new UnexpectedTypeException($data, 'array or \Traversable');
        }

        // First remove all rows except for the prototype row
        foreach ($form as $name => $child) {
            if (!($this->allowAdd && '$$name$$' === $name)) {
                $form->remove($name);
            }
        }

        // Then add all rows again in the correct order
        foreach ($data as $name => $value) {
            $form->add($this->factory->createNamed($this->type, $name, null, array_merge(array(
                'property_path' => '['.$name.']',
            ), $this->typeOptions)));
        }
    }

    public function preBind(DataEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data || '' === $data) {
            $data = array();
        }

        if (!is_array($data) && !$data instanceof \Traversable) {
            throw new UnexpectedTypeException($data, 'array or \Traversable');
        }

        // Remove all empty rows except for the prototype row
        if ($this->allowDelete) {
            foreach ($form as $name => $child) {
                if (!isset($data[$name]) && '$$name$$' !== $name) {
                    $form->remove($name);
                }
            }
        }

        // Add all additional rows
        if ($this->allowAdd) {
            foreach ($data as $name => $value) {
                if (!$form->has($name)) {
                    $form->add($this->factory->createNamed($this->type, $name, null, array_merge(array(
                        'property_path' => '['.$name.']',
                    ), $this->typeOptions)));
                }
            }
        }
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !$data instanceof \Traversable) {
            throw new UnexpectedTypeException($data, 'array or \Traversable');
        }

        if ($this->allowDelete) {
            foreach ($data as $name => $child) {
                if (!$form->has($name)) {
                    unset($data[$name]);
                }
            }
        }

        $event->setData($data);
    }
}
