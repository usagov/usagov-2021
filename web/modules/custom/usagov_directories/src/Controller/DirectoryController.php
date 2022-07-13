<?php
namespace Drupal\usagov_directories\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Controller\NodeViewController;
use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;


/** 
 * Provides routes for A-Z directories. 
 */
class DirectoryController extends ControllerBase {

    public static function create(ContainerInterface $container) {
        return new static(
          $container
        );
    }
    
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /*
     * We get a path like /federal-agencies/a, and want to 
     * render the page at /federal-agencies. (The router confirms that we've getting a single 
     * letter or digit.)
     */
    public function federalAgenciesPage($letter) {

        // WIP: I've tried a few things here. 
        // The bit at the bottom illustrates partial success, but not all parts of the page 
        // render. Also, the views work better if we can use a query string parameter instead 
        // of a path part to render a "block" part of a view. So, ideally, we'd catch the 
        // request early on and change the pathinfo and requesturi from "/federal-agencies/d" 
        // (for example) to "/federal-agencies" and "/federal-agencies?letter=d" and let Drupal 
        // take it from there. I don't know whether this is a thing we can do. 

        // $nids = \Drupal::entityQuery('node')
        // ->condition('')
        // $nodes = $this->getStorage('node')->loadById(584);
        
        // $entity_type_manager = $this->container->get('entity_type.manager');
        // $node = $entity_type_manager->getStorage('node')->load(584);
        // $nodeController = NodeViewController::create($this->container);
        // return $nodeController->view($node, 'full');

	    // $kernel = new DrupalKernel('prod', $autoloader);
 	

        $entity_type_manager = $this->container->get('entity_type.manager');
        $node = $entity_type_manager->getStorage('node')->load(584);
        $view_mode = 'full';
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $render_controller = \Drupal::entityTypeManager()->getViewBuilder($node->getEntityTypeId());
        $render_output = $render_controller->view($node, $view_mode, $langcode);
        return $render_output;
    }
}


