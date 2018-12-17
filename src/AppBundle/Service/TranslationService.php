<?php

namespace AppBundle\Service;

use AppBundle\Entity\Article;
use AppBundle\Entity\ArticleBis;
use AppBundle\Entity\ArticleBisTranslation;
use AppBundle\Entity\ArticleTer;
use AppBundle\Entity\ArticleTerTranslation;
use AppBundle\Entity\ArticleTranslation;
use AppBundle\Entity\Block;
use AppBundle\Entity\BlockBis;
use AppBundle\Entity\BlockBisTranslation;
use AppBundle\Entity\BlockTerTranslation;
use AppBundle\Entity\BlockTranslation;
use AppBundle\Entity\Dog;
use AppBundle\Entity\DogTranslation;
use AppBundle\Entity\Kitten;
use AppBundle\Entity\KittenTranslation;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBis;
use AppBundle\Entity\PageBisTranslation;
use AppBundle\Entity\PageTer;
use AppBundle\Entity\PageTerTranslation;
use AppBundle\Entity\PageTranslation;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Google\Cloud\Translate\TranslateClient;
use Proxies\__CG__\AppBundle\Entity\BlockTer;
use ReflectionClass;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;

class TranslationService
{
    /** @var EntityManagerInterface $em */
    protected $em;

    /** @var TranslateClient $translate */
    protected $translate;

    /**
     * 500 000 caracteres/day
     */
    const QUOTA_JOUR = 500000;

    /**
     * 1000 caractere/100sec
     */
    const QUOTA = 1000;

    protected $fields = ['title','title2','title3','title4','content','header','footer', 'custom', 'content2',
                            'content3', 'content4', 'race', 'color', 'description', 'subtitle'];

    public function __construct(EntityManagerInterface $em, string $googleTranslateApi)
    {
        $this->em = $em;

        $this->translate = new TranslateClient(array("key" => $googleTranslateApi));
    }

    public function translate($entity, $language)
    {
        try {
            $reflectionClass = new ReflectionClass("AppBundle\Entity\\" . $entity . "Translation");
        } catch (\ReflectionException $e) {
            return $e->getMessage();
        }

        $classToTranslate = $this->em->getRepository($reflectionClass->getName())->findAll();

        $properties = $reflectionClass->getProperties(\ReflectionMethod::IS_PRIVATE);

        try {
            foreach ($classToTranslate as $content) {

                $contentDb = $this->em->getRepository($reflectionClass->getName())->findOneBy(array(
                    "locale" => $language,
                    "translatable" => $content->getTranslatable()
                ));

                if ($contentDb) {
                    foreach ($properties as $property) {
                        var_dump($property);
                        $data = $content->{'get' . ucfirst($property->getName())}();

                        if (mb_strlen($data) >= self::QUOTA) {
                            $divideData = str_split($data, self::QUOTA);
                            $arrayToTranslate = array();
                            foreach ($divideData as $item) {
                                $arrayToTranslate[] = $this->translate->translate($item, array("target" => $language));
                            }

                            $arrayTranslated = implode('', $arrayToTranslate);
                            $contentDb->{'set' . ucfirst($property->getName())}($arrayTranslated);

                        } else {
                            $dataTranslate = $this->translate->translate($data, array("target" => $language));
                            $contentDb->{'set' . ucfirst($property->getName())}($dataTranslate['text']);
                        }
                    }

                    $this->em->flush();
                } else {
                    $newContent = $reflectionClass->newInstance();

                    foreach ($properties as $property) {
                        $data = $content->{'get' . ucfirst($property->getName())}();

                        if (mb_strlen($data) >= self::QUOTA) {
                            $divideData = str_split($data, self::QUOTA);
                            $arrayToTranslate = array();
                            foreach ($divideData as $item) {
                                $arrayToTranslate[] = $this->translate->translate($item, array("target" => $language));
                            }

                            $arrayTranslated = implode('', $arrayToTranslate);
                            $newContent->{'set' . ucfirst($property->getName())}($arrayTranslated);

                        } else {
                            $dataTranslate = $this->translate->translate($data, array("target" => $language));
                            $newContent->{'set' . ucfirst($property->getName())}($dataTranslate['text']);
                        }
                    }
                    $newContent->setTranslatable($content->getTranslatable());
                    $newContent->setLocale($language);

                    $this->em->persist($newContent);
                    $this->em->flush();
                }
            }
        } catch (\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            if ($error['error']['message'] == "User Rate Limit Exceeded") {
                sleep(100);
                return $this->translate($entity, $language);
            } else {
                var_dump($error['error']['message']);
                die();
            }
        }
    }
}