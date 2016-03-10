# phalcon-treemodel

Phalcon model for handling hierarchical data

## Examples
* Extending model
```php
class Category extends \Londo\TreeModel {

}
```
* Finding node
```php
$category = Category::findNode(1); 
// or
$category = Category::findNode(array('id' => 1));
```
* Finding parent
```php
$category = Category::findNode(5);
$parent = $category->parent();
```
* 
