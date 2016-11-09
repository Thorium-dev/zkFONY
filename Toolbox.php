<?php

namespace AppBundle\Service;

use Symfony\Component\Config\Definition\Exception\Exception;

//error_reporting(0);

class Toolbox
{
    private $container;
    private $rootDir;

    public function __construct($container)
    {

        // Set container and rootDir
        if(isset($container)){
            $this->container = $container;
            $this->rootDir = $container->getParameter('kernel.root_dir')."/../";
        }

    }

    /**
     * Get base root directory
     *
     * @return string
     */
    public function getRoot(){
        return $this->rootDir;
    }


}