# SimpleLucene library

## Goal

Provide a simple interface on Zend_Lucene library for: maintenance index, add documents to the index, search in the index.

## Usage

The lucene index stores documents. If you add a new version of a document, you have to remove the previous version before. Every lucene document has a related "object".

The DocumentProviderInterface provides a simple interface to create lucene document. This way is is possible to create Zend_Lucene_Document by the object itself, or delegate the task to another object.

ex.

    class Foo implements SimpleLucene_DocumentProviderInterface
    {
        public $id;
        public $title;
        public $description;
        // ...
        
        function getLuceneDocument($foo)
        {
            $doc = new Zend_Search_Lucene_Document();
            return $doc
                ->addField(Zend_Search_Lucene_Field::unIndexed('_id', $this->id, 'utf-8'))
                ->addField(Zend_Search_Lucene_Field::Text('title', $this->title, 'utf-8'))
                ->addField(Zend_Search_Lucene_Field::unStored('description', $this->description, 'utf-8'));
        }

        function getLuceneDocumentUniqueId($foo)
        {
            return sha1('Foo:'.$this->id);
        }
        
        // ...
    }
    
or delegate

    class FooDocumentProvider implements SimpleLucene_DocumentProviderInterface
    {
        function getLuceneDocument($foo)
        {
            $doc = new Zend_Search_Lucene_Document();
            return $doc
                ->addField(Zend_Search_Lucene_Field::unIndexed('_id', $foo->id, 'utf-8'))
                ->addField(Zend_Search_Lucene_Field::Text('title', $foo->title, 'utf-8'))
                ->addField(Zend_Search_Lucene_Field::unStored('description', $foo->description, 'utf-8'));
        }

        function getLuceneDocumentUniqueId($foo)
        {
            return sha1('Foo:'.$foo->id);
        }
    }



### Add documents to the index

Lets follow the example. Add foo document to the index.

    $lucene = new SimpleLucene_Manager($index_dir);
    $foo = new Foo;
    
    // when Foo implements the document provider interface
    $lucene->removeFromIndex($foo);
    $lucene->addToIndex($foo);
    
    // when Foo delegate the document provider interface
    $lucene->addDocumentProvider('Foo', new FooDocumentProvider);
    $lucene->removeFromIndex($foo);
    $lucene->addToIndex($foo);

### Search in the index

You have to create a search query object.

    class FooSearchQuery implements SimpleLucene_SearchQueryInterface
    {
        function getLuceneSearchQuery($phrase)
        {
            $query = new Zend_Search_Lucene_Search_Query_Boolean();

            foreach (explode(' ', $phrase) as $part) {
                $part = "$part~";
                
                $pattern = new Zend_Search_Lucene_Index_Term($part, 'title');
                $query->addSubQuery(new Zend_Search_Lucene_Search_Query_Fuzzy($pattern));
                
                $pattern = new Zend_Search_Lucene_Index_Term($part, 'description');
                $query->addSubQuery(new Zend_Search_Lucene_Search_Query_Fuzzy($pattern));
            }
            return $query;
        }
    }

When you search just create a manager, set the search query from above, and search.

    $lucene = new SimpleLucene_Manager($index_dir);
    $lucene->setSearchQuery(new FooSearchQuery);
    $lucene->search('foo and bar');

### Index optimization

Over and over again the index starts growing large and working slower and slower. It will contain irrelevant/obsoleted documents. You have to clean up the mess. Lets do it.

    $lucene = new SimpleLucene_Manager($index_dir);
    $lucene->optimize();


## Symfony related stuff

The symfony manager provides a `listenToChanges` method, which can listen to events indexable object changes. For example: `admin.save_object` or `admin.delete_object`. You can register it to those events.

    $lucene = new SimpleLucene_Manager($index_dir);
    
    $dispatcher->connect('admin.save_object', array($lucene, 'listenToChanges'));

Thats all. It will looking for `sfEvent` instance `object` attribute. Any object that implements `SimpleLucene_DocumentProviderInterface` is indexable. Otherwise you have to register a document provider before you do anything.

    $lucene = new SimpleLucene_Manager($index_dir);
    $lucene->addDocumentProvider('Foo', new FooDocumentProvider);
    
    $dispatcher->connect('admin.save_object', array($lucene, 'listenToChanges'));


## Tipps and tricks

The library supports multiple index: just create a new instance on different index directory.
