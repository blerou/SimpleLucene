<?php

/*
 * This file is part of the SimpleLucene library.
 *
 * Copyright (c) 2009-2010 Szabolcs Sulik <sulik.szabolcs@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * symfony related manager class
 *
 * @package SimpleLucene
 * @author  blerou
 */
class SimpleLucene_ManagerSymfony extends SimpleLucene_Manager
{
    /**
     * remove only events list
     *
     * @var array
     */
    private $deleteEvents = array('admin.delete_object');

    /**
     * index updater event listener
     *
     * @param  sfEvent $event
     */
    public function listenToChanges(sfEvent $event)
    {
        $object = $event['object'];

        $delete_only = in_array($event->getName(), $this->deleteEvents);

        $this->removeFromIndex($object);

        if (!$delete_only) {
            $this->addToIndex($object);
        }
    }

    /**
     * add a remove only event
     *
     * @param  string $event_name
     * @return SimpleLucene_Manager
     */
    public function addDeleteEvent($eventName) {
        $this->deleteEvents[] = $eventName;
        return $this;
    }
}
