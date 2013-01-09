<?php

/*
 * This file is part of the reportPlugin package.
 * (c) 2011-2012 Juan Manuel Fernandez <juanmf@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This is an implementation of the Composite pattern that handles Step 2 of a 
 * three Steps Process for report Generation.
 *
 * This class' objects can have a collection of BasicView Objects.
 * 
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 * @see    BasicView
 */
class CompositeView extends BasicView
{
    /**
     * Holds a reference to each childView.
     * //Todo change to private after fixing CodeSniffer bug
     * @var array 
     */
    public $children = array();
    
    /**
     * Used to make the XQuery to find childs defaults to 'child_view'
     * @var string
     */
    public static $childViewNodeName = 'child_view';

    /**
     * This is the interface method of the composite pattern, that enable 
     * CompositeView to create a collection of Child Views.
     * 
     * @param string    $name The child View's name. As described in this class'
     * DocBlock. <b><child_view name="ChildName"/></b>.
     * @param BasicView $view The Child BasicView object.
     * 
     * @see BasicView, LayoutManager::_makeCompositeView()
     * @return void
     */
    public function addView($name, BasicView $view)
    {
        $this->children[$name] = $view;
        $view->parent = $this;
    }

    /**
     * Returns a Child BasicView object by name.
     * 
     * This is the interface method of the composite pattern, that enable 
     * CompositeView to create a collection of Child Views.
     * 
     * @param string $name The child View's name. As described in this class'
     * DocBlock. <b><child_view name="ChildName"/></b>.
     * 
     * @return BasicView The ChildView with name $name
     */
    public function getChildView($name)
    {
        return $this->children[$name];
    }

    /**
     * Removes a Child BasicView object.
     * 
     * This is the interface method of the composite pattern, that enable 
     * CompositeView to create a collection of Child Views.
     * 
     * @param string $name The child View's name. As described in this class'
     * DocBlock. <b><child_view name="ChildName"/></b>.
     * 
     * @see BasicView::addView()
     * 
     * @return void
     */
    public function removeView($name)
    {
        unset($this->children[$name]);
    }
    
    /**
     * Returns a DOMDocument with this view's processed structure and Style 
     * Stylesheets, the result is expected to be an XSL-FO document portion, if
     * we are using <i>children</i> nodes for this node in config i.e. a tree 
     * hierarchy report defnition, or a complete XSL-FO document of this composite 
     * is the root CompositeView Object.
     * 
     * In the Recursive Step2 render process the Root stylesheet first calls it's 
     * child Stylesheets which in turn does the same. Then, when all child are rendered
     * the Stylesheet's <b><child_view name="ChildName"/></b> Tags get replaced by the
     * XSL-FO part generated by <b>"ChildName"</b> Stylesheet. The result being the 
     * complete XSL-FO representation of the report, delivered by Step2.
     * 
     * This method 1st calls every child BasicView object to render itself. Then
     * render this CompositeView's stylesheets and in the rendered XSL-FO documents
     * it looks for every <b><child_view name="ChildName"/></b> and replaces it 
     * with the corresponding rendered child using name attribute to match them.
     * 
     * @see BasicView, BasicView::_renderedDom
     * @return DOMDocument
     */
    public function render()
    {
        $xQuery = '//' . self::$childViewNodeName;
        $childFo = array();
        $childNodes = $this->_structure->xpath($xQuery);
        reset($childNodes);
        while (list($key, $ch) = each($childNodes)) {
            /* @var $ch SimpleXMLElement */
            $name = (string)$ch->attributes()->name;
            // renders every child present in $_structure xslt and in $_children
            if (isset($this->children[$name])) {
                $childView = $this->children[$name];
                $childFo[$name] = $childView->render();
            } else {
                $ch->addAttribute('child_not_found', 1);
            }
        }
        $structTran = parent::render();
        self::$_xPath = new DOMXPath($structTran);
        $childDomNodes = self::$_xPath->query($xQuery);
        foreach ($childDomNodes as $childDn) {
            /* Replace every $this->childNiewNodeName node by its rendered
             * subview.
             */
            $name = $childDn->getAttribute('name');
            $replacementNodes = $childFo[$name]->childNodes;
            /* @var $replacementNodes DOMElement */
            foreach ($replacementNodes as $replaceNode) {
                $newChildNode = $structTran->importNode($replaceNode, true);
                $childDn->parentNode->insertBefore($newChildNode, $childDn);
            }
            $childDn->parentNode->removeChild($childDn);
        }
        $this->_renderedDom = $structTran;
        return $this->_renderedDom;
    }
}
