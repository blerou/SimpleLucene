<?php
require 'symfony/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/../../lib/vendor');
require 'Zend/Search/Lucene.php';

require_once dirname(__FILE__).'/../lib/lime.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/DocumentProviderInterface.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/SearchQueryInterface.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/Manager.php';
require_once dirname(__FILE__).'/../../src/SimpleLucene/ManagerSymfony.php';

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
// }}} test classes

$indexDir = dirname(__FILE__).'/test_index';
$docProv = new TestAll;

$t = new lime_test(2);


$t->diag('listening');


clear_index_dir($indexDir, true);
$lucene = new SimpleLucene_ManagerSymfony($indexDir);
$lucene->addDeleteEvent('foo.bar');
$lucene->setDocumentProviders(array('Foo' => $docProv));
$lucene->addToIndex(new Foo);

$lucene->listenToChanges(new sfEvent(new stdClass, 'foo.bar', array('object' => new Foo)));
$t->is($lucene->getIndex()->numDocs(), 0, '->listenToChanges() only delete from index on delete events');

$lucene->listenToChanges(new sfEvent(new stdClass, 'test.bar', array('object' => new Foo)));
$t->is($lucene->getIndex()->numDocs(), 1, '->listenToChanges() works fine');


clear_index_dir($indexDir, true);

function clear_index_dir($index_dir, $drop = false)
{
  if (!file_exists($index_dir))
  {
    @mkdir($index_dir);
    @chmod($index_dir, 0777);
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