<?php
require 'symfony/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();
require 'Zend/Search/Lucene.php';

require_once dirname(__FILE__).'/../lib/lime.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/DocumentProviderInterface.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/SearchQueryInterface.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/Manager.php';

// {{{ test classes
class TestAll implements SimpleLucene_DocumentProviderInterface, SimpleLucene_SearchQueryInterface
{
  public function getLuceneDocument($object)
  {
    $doc = new Zend_Search_Lucene_Document();
    
    return $doc->addField(Zend_Search_Lucene_Field::Text('foo', 'bar', 'utf-8'));
  }
  
  public function getLuceneDocumentUniqueId($object)
  {
    return sha1('asdf');
  }
  
  public function getLuceneSearchQuery($query)
  {
    $term = new Zend_Search_Lucene_Index_Term($query, 'foo');
    
    return new Zend_Search_Lucene_Search_Query_Term($term);
  }
}

class Foo {}

class Bar {}

class FooProvider implements SimpleLucene_DocumentProviderInterface
{
  public function getLuceneDocument($foo)
  {
    return false;
  }
  
  public function getLuceneDocumentUniqueId($foo)
  {
    return sha1('asdf');
  }
}
// }}} test classes

$index_dir = dirname(__FILE__).'/test_index';
$doc_prov = new TestAll;
$foo_prov = new FooProvider();


$t = new lime_test(19);


$t->diag('index');


clear_index_dir($index_dir, true);

$lucene = new SimpleLucene_Manager($index_dir);

$t->ok($lucene->getIndex() instanceof Zend_Search_Lucene_Interface, '->getIndex() returns a Zend_Search_Lucene_Interface');
$t->ok(is_dir($index_dir) === true, '->getIndex() creates index directory');

clear_index_dir($index_dir);

$lucene = new SimpleLucene_Manager($index_dir);

$t->ok(is_dir($index_dir) === true, '->getIndex() uses existing empty index directory');

$lucene->setDocumentProviders(array('Foo' => $doc_prov));
$lucene->addToIndex(new Foo);
$lucene = new SimpleLucene_Manager($index_dir);
$lucene->getIndex();

$t->pass('->getIndex() uses existing index directory');


$t->diag('document providers');


$lucene = new SimpleLucene_Manager($index_dir);

$t->is_deeply($lucene->getDocumentProviderFor(new stdClass), null, '->getDocumentProviderFor() returns null for an unregistered class');

$lucene->addDocumentProvider('Bar', $doc_prov);

$t->is_deeply($lucene->getDocumentProviderFor(new Bar), $doc_prov, '->addDocumentProvider(), ->getDocumentProviders() works fine');

$lucene->setDocumentProviders(array('Foo' => $doc_prov));

$t->ok(
  $lucene->getDocumentProviderFor(new Foo) === $doc_prov
  && $lucene->getDocumentProviderFor(new Bar) === null
  , '->setDocumentProviders() works fine'
);

$t->is_deeply($lucene->getDocumentProviderFor(new Foo), $doc_prov, '->getDocumentProviderFor() returns correct provider for a registered class');
$t->is_deeply($lucene->getDocumentProviderFor($foo_prov), $foo_prov, '->getDocumentProviderFor() returns object itself if implements interface');


$t->diag('index handling');


clear_index_dir($index_dir);
$lucene = new SimpleLucene_Manager($index_dir);

$lucene->addToIndex(new stdClass());

$t->is($lucene->getIndex()->numDocs(), 0, '->addToIndex() do nothing on unregistered objects');

$lucene->addToIndex($foo_prov);

$t->is($lucene->getIndex()->numDocs(), 0, '->addToIndex() do nothing on objects that returns false in getLuceneDocument() method');

$lucene->addDocumentProvider('Foo', $doc_prov);
$lucene->addToIndex(new Foo);

$t->is($lucene->getIndex()->numDocs(), 1, '->addToIndex() create index');

$lucene->removeFromIndex(new stdClass);

$t->is($lucene->getIndex()->numDocs(), 1, '->removeFromIndex() do nothing with unregistered classes');

$lucene->removeFromIndex(new Foo);

$t->is($lucene->getIndex()->numDocs(), 0, '->removeFromIndex() works fine');


$t->diag('search');


$lucene = new SimpleLucene_Manager($index_dir);
$lucene->addDocumentProvider('Foo', $doc_prov);
$lucene->addToIndex(new Foo);

try
{
  $lucene->getSearchQuery();
  
  $t->fail('->getSearchQuery() must throws exception before search query initialized');
}
catch (Exception $e)
{
  $t->pass('->getSearchQuery() throws exception before search query initialized');
}

$lucene->setSearchQuery($doc_prov);

$t->is_deeply($lucene->getSearchQuery(), $doc_prov, '->setSearchQuery() works fine');
  
$hits = $lucene->search('foo');

$t->is(count($hits), 0, '->search() works fine with unindexed phrase');

  
$hits = $lucene->search('bar');

$t->is(count($hits), 1, '->search() works fine with indexed phrase');


$t->diag('optimization');


$indexCnt = $lucene->getIndex()->count();
$lucene->optimize();

$t->ok($lucene->getIndex()->count() < $indexCnt, '->optimize() works fine');



clear_index_dir($index_dir, true);


function clear_index_dir($index_dir, $drop = false)
{
  if (!file_exists($index_dir))
  {
    return;
  }
  
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($index_dir), RecursiveIteratorIterator::LEAVES_ONLY) as $file)
  {
    @unlink($file->getRealPath());
  }
  
  if ($drop)
  {
    @rmdir($index_dir);
  }
}
