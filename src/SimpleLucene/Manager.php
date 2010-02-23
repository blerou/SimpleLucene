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
 * manage lucene index / search in the index
 *
 * @package SimpleLucene
 * @author  blerou
 */
class SimpleLucene_Manager
{
  /**
   * lucene index instance
   *
   * @var Zend_Search_Lucene_Interface
   */
  private $index = null;
  
  /**
   * place of lucene index
   *
   * @var string
   */
  private $indexDir;
  
  /**
   * @var SimpleLucene_SearchQueryInterface
   */
  private $searchQuery = null;
  
  /**
   * lucene document providers
   *
   * @var array
   */
  private $documentProviders = array();

  /**
   * Constructor
   *
   * @param  string $index_dir
   */
  public function __construct($index_dir)
  {
    $this->indexDir = $index_dir;
  }
  
  /**
   * search for the given phrase
   *
   * @param  string $words
   * @return Zend_Search_Lucene_Search_QueryHit
   */
  public function search($words)
  {
    return $this->getIndex()->find($this->getSearchQuery()->getLuceneSearchQuery($words));
  }
  
  /**
   * index optimization
   *
   * @return void
   */
  public function optimize()
  {
    $index = $this->getIndex();
    $index->setMaxBufferedDocs(1);
    $index->setMaxMergeDocs(1);
    $index->setMergeFactor(1);
    $index->optimize();
  }
  
  /**
   * add an object to the index
   *
   * @param  object $object
   * @return void
   */
  public function addToIndex($object)
  {
    $document_provider = $this->getDocumentProviderFor($object);
    
    if (!$document_provider) {
      return;
    }
    
    $doc = $document_provider->getLuceneDocument($object);
      
    if ($doc === false) {
      return;
    }
    
    $doc->addField(Zend_Search_Lucene_Field::Keyword('_lucene_uid', $document_provider->getLuceneDocumentUniqueId($object)));
    
    $this->getIndex()->addDocument($doc);
    $this->getIndex()->commit();
  }
  
  /**
   * remove an object from the index
   *
   * @param  object $object
   * @return void
   */
  public function removeFromIndex($object)
  {
    $index = $this->getIndex();
    
    $document_provider = $this->getDocumentProviderFor($object);
    
    if (!$document_provider) {
      return;
    }
    
    $hits = $index->find('_lucene_uid:'.$document_provider->getLuceneDocumentUniqueId($object));
    
    foreach ($hits as $hit) {
      $index->delete($hit->id);
    }
  }
  
  /**
   * add a lucene document provider
   *
   * @param  string $class
   * @param  SimpleLucene_DocumentProviderInterface $provider
   * @return SimpleLucene_Manager
   */
  public function addDocumentProvider($class, SimpleLucene_DocumentProviderInterface $provider)
  {
    $this->documentProviders[$class] = $provider;
    return $this;
  }
  
  /**
   * add lucene document providers
   *
   * @param  array $providers
   * @return SimpleLucene_Manager
   */
  public function setDocumentProviders(array $providers)
  {
    $this->documentProviders = array();
    foreach ($providers as $class => $provider) {
      $this->addDocumentProvider($class, $provider);
    }
    return $this;
  }

  /**
   * get the related document provider for the given object
   *
   * @param  object $object
   * @return SimpleLucene_DocumentProviderInterface|null
   */
  public function getDocumentProviderFor($object)
  {
    $class = get_class($object);
    
    $document_providers = $this->documentProviders;
    
    if ($object instanceof SimpleLucene_DocumentProviderInterface) {
      return $object;
    } elseif (isset($document_providers[$class])) {
      return $document_providers[$class];
    }
  }
  
  /**
   * get the lucene index instance
   *
   * @return Zend_Search_Lucene_Interface
   */
  public function getIndex()
  {
    if (!$this->index) {
      if (!file_exists($this->indexDir)) {
        $mask = umask(0);
        mkdir($this->indexDir, 0777, true);
        umask($mask);
        
        $this->index = Zend_Search_Lucene::create($this->indexDir);
      } elseif (!file_exists($this->indexDir.'/segments.gen')) {
        $this->index = Zend_Search_Lucene::create($this->indexDir);
      } else {
        $this->index = Zend_Search_Lucene::open($this->indexDir);
      }
    }
    return $this->index;
  }
  
  /**
   * get the current search query instance
   *
   * @return SimpleLucene_SearchQueryInterface
   * @throws Exception
   */
  public function getSearchQuery()
  {
    if (!$this->searchQuery) {
      throw new Exception('A searchQuery osztaly beallitasa kotelezo.');
    }
    return $this->searchQuery;
  }
  
  /**
   * add the search query instance
   *
   * @param  SimpleLucene_SearchQueryInterface $query
   * @return SimpleLucene_Manager
   */
  public function setSearchQuery(SimpleLucene_SearchQueryInterface $query)
  {
    $this->searchQuery = $query;
    return $this;
  }
}
