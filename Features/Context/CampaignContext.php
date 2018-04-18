<?php

namespace Itk\CampaignBundle\Features\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behatch\Context\BaseContext;
use Behatch\HttpCall\HttpCallResultPool;
use Behatch\HttpCall\Request;
use Behatch\Json\Json;
use Behatch\Json\JsonInspector;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaTool;
use Os2Display\CoreBundle\Entity\Channel;
use Os2Display\CoreBundle\Entity\ChannelScreenRegion;
use Os2Display\CoreBundle\Entity\ChannelSlideOrder;
use Os2Display\CoreBundle\Entity\SharedChannel;
use Os2Display\CoreBundle\Entity\User;
use Os2Display\CoreBundle\Entity\UserGroup;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Os2Display\CoreBundle\Entity\Slide;
use Os2Display\CoreBundle\Entity\Screen;
use JMS\Serializer\SerializationContext;

/**
 * Defines application features from the specific context.
 */
class CampaignContext extends BaseContext implements Context, KernelAwareContext
{
    private $kernel;
    private $container;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;
    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    private $manager;

    /**
     * @var \Behatch\HttpCall\Request
     */
    private $request;

    /**
     * @var \Behatch\Json\JsonInspector
     */
    private $inspector;

    /**
     * @var \Behatch\HttpCall\HttpCallResultPool
     */
    private $httpCallResultPool;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     *
     * @param mixed $evaluationMode
     */
    public function __construct(
        ManagerRegistry $doctrine,
        Request $request,
        HttpCallResultPool $httpCallResultPool,
        $evaluationMode = 'javascript'
    ) {
        $this->doctrine = $doctrine;
        $this->request = $request;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();

        $this->inspector = new JsonInspector($evaluationMode);
        $this->httpCallResultPool = $httpCallResultPool;
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->container = $this->kernel->getContainer();
    }

    private function createChannel(array $data)
    {
        $repository = $this->doctrine->getManager()->getRepository(
            Channel::class
        );

        $channel = $repository->findOneBy(['title' => $data['title']]);
        if (!$channel) {
            $channel = new Channel();
            $channel->setTitle($data['title']);
            $channel->setCreatedAt($data['created_at']);
            $channel->setModifiedAt($data['modified_at']);
            $this->doctrine->getManager()->persist($channel);
            $this->doctrine->getManager()->flush();
        }
    }

    /**
     * @Given the following channels exist:
     */
    public function theFollowingChannelsExist(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            $this->createChannel($row);
        }
        $this->doctrine->getManager()->clear();
    }

    private function createSlide(array $data)
    {
        $repository = $this->doctrine->getManager()->getRepository(
            Slide::class
        );
        $channelsRepository = $this->doctrine->getManager()->getRepository(
            Channel::class
        );

        $slide = $repository->findOneBy(['title' => $data['title']]);
        if (!$slide) {
            $slide = new Slide();
            $slide->setTitle($data['title']);
        }

        foreach ([$data['channel']] as $channel) {
            $channel = $channelsRepository->findOneBy(['id' => $channel]);

            $channelSlideOrder = new ChannelSlideOrder();
            $channelSlideOrder->setChannel($channel);
            $channelSlideOrder->setSlide($slide);
            $channelSlideOrder->setSortOrder(0);
        }

        $this->doctrine->getManager()->persist($slide);
    }

    /**
     * @Given the following slides exist:
     */
    public function theFollowingSlidesExist(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            $title = $row['title'];
            $channel = $row['channel'];

            $this->createSlide(['title' => $title, 'channel' => $channel]);
        }
        $this->doctrine->getManager()->clear();
    }

    /**
     * @When I call pushToScreens
     */
    public function iCallPushtoscreens()
    {
        $this->container->get('os2display.middleware.communication')
            ->pushToScreens();
    }

    /**
     * @When I get the last utility service curl call
     */
    public function iGetTheLastUtilityServiceCurlCall()
    {
        return $this->container->get('os2display.utility_service')
            ->getLastRequest();
    }

    /**
     * @When I get all the utility service curl calls
     */
    public function iGetAllTheUtilityServiceCurlCalls()
    {
        $this->curlCalls = $this->container->get('os2display.utility_service')
            ->getAllRequests();
    }

    /**
     * @When I get all the utility service curl calls with prefix :prefix
     */
    public function iGetAllTheUtilityServiceCurlCallsWithPrefix($prefix)
    {
        $this->curlCalls = $this->container->get('os2display.utility_service')
            ->getAllRequests($prefix);
    }

    /**
     * @When I print all the utility service curl calls
     */
    public function iPrintAllTheUtilityServiceCurlCalls()
    {
        var_dump(
            $this->container->get('os2display.utility_service')->getAllRequests(
            )
        );
    }

    /**
     * @Then curl calls should equal:
     */
    public function curlCallsShouldEqual(TableNode $table)
    {
        $shouldEqual = $table->getHash();

        $this->assertEquals(
            $this->curlCalls,
            $shouldEqual,
            sprintf(
                'The array "%s" should be an equal the input',
                json_encode($this->curlCalls)
            )
        );
    }

    private function createScreen(array $data)
    {
        $manager = $this->doctrine->getManager();

        $repository = $manager->getRepository(
            Screen::class
        );
        $channelRepository = $manager->getRepository(
            Channel::class
        );

        $screen = $repository->findOneBy(['title' => $data['title']]);
        if (!$screen) {
            $screen = new Screen();
            $screen->setTitle($data['title']);
            $screen->setCreatedAt($data['created_at']);
            $screen->setModifiedAt($data['modified_at']);
            $screen->setToken('123');
            $screen->setActivationCode('123');
            $screen->setDescription('312');

            $manager->persist($screen);

            if (!empty($data['channel'])) {
                foreach ([$data['channel']] as $channel) {
                    $channel = $channelRepository->findOneBy(
                        ['id' => $channel]
                    );

                    $csr = new ChannelScreenRegion();
                    $csr->setChannel($channel);
                    $csr->setScreen($screen);
                    $csr->setRegion(1);
                    $manager->persist($csr);

                    $channel->addChannelScreenRegion($csr);
                    $screen->addChannelScreenRegion($csr);
                }
            }

            $manager->flush();
        }
    }

    /**
     * @Given the following screens exist:
     */
    public function theFollowingScreensExist(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            $this->createScreen($row);
        }
        $this->doctrine->getManager()->clear();
    }

    private function channelPushedToScreen($channel, $screen, $result = true)
    {
        $requests = $this->container->get('os2display.utility_service')
            ->getAllRequests('middleware');

        $res = false;

        foreach ($requests as $request) {
            if (explode(
                    'https://middleware.os2display.vm/api/channel/',
                    $request['url']
                )[1] == $channel) {
                if ($request['method'] == 'POST') {
                    $data = json_decode($request['data']);
                    if (in_array($screen, $data->screens)) {
                        $res = true;
                        break;
                    }
                }
            }
        }

        $this->assertEquals(
            $res,
            $result,
            sprintf(
                'The channel "%s" should'.(!$result ? ' not' : '').' be pushed to screen "%s"',
                $channel,
                $screen
            )
        );
    }

    /**
     * @Then channel :arg1 should be pushed to screen :arg2
     */
    public function channelShouldBePushedToScreen($arg1, $arg2)
    {
        $this->channelPushedToScreen($arg1, $arg2);
    }

    /**
     * @Then channel :arg1 should not be pushed to screen :arg2
     */
    public function channelShouldNotBePushedToScreen($arg1, $arg2)
    {
        $this->channelPushedToScreen($arg1, $arg2, false);
    }

    /**
     * @Then channel :arg1 should be deleted from middleware
     */
    public function channelShouldBeDeletedFromMiddleware($arg1)
    {
        $requests = $this->container->get('os2display.utility_service')
            ->getAllRequests('middleware');

        $res = false;

        foreach ($requests as $request) {
            if (explode(
                    'https://middleware.os2display.vm/api/channel/',
                    $request['url']
                )[1] == $arg1) {
                if ($request['method'] == 'DELETE') {
                    $res = true;
                    break;
                }
            }
        }

        $this->assertEquals(
            $res,
            true,
            sprintf(
                'The channel "%s" should be removed from the middleware',
                $arg1
            )
        );
    }

    private function channelPushedToScreenRegion(
        $channel,
        $screen,
        $region,
        $result = true
    ) {
        $requests = $this->container->get('os2display.utility_service')
            ->getAllRequests('middleware');

        $res = false;

        foreach ($requests as $request) {
            if (explode(
                    'https://middleware.os2display.vm/api/channel/',
                    $request['url']
                )[1] == $channel) {
                if ($request['method'] == 'POST') {
                    $data = json_decode($request['data']);

                    foreach ($data->regions as $dataRegion) {
                        if ($dataRegion->region == $region && $dataRegion->screen == $screen) {
                            $res = true;
                            break;
                        }
                    }
                }
            }
        }

        $this->assertEquals(
            $res,
            $result,
            sprintf(
                'The channel "%s" should'.(!$result ? ' not' : '').' be pushed to screen "%s" in region "%s"',
                $channel,
                $screen,
                $region
            )
        );
    }

    /**
     * @Then channel :arg1 should be pushed to screen :arg2 region :arg3
     */
    public function channelShouldBePushedToScreenRegion($arg1, $arg2, $arg3)
    {
        $this->channelPushedToScreenRegion($arg1, $arg2, $arg3);
    }

    /**
     * @Then channel :arg1 should not be pushed to screen :arg2 region :arg3
     */
    public function channelShouldNotBePushedToScreenRegion($arg1, $arg2, $arg3)
    {
        $this->channelPushedToScreenRegion($arg1, $arg2, $arg3, false);
    }

    /**
     * @When I clear utility service
     */
    public function clearUtilityService()
    {
        $this->container->get('os2display.utility_service')->clear();
    }

    /**
     * @When I clear all channels
     */
    public function clearAllChannels()
    {
        $channels = $this->doctrine->getRepository(
            'Os2DisplayCoreBundle:Channel'
        )->findAll();

        foreach ($channels as $channel) {
            $channel->setLastPushHash(null);
            $channel->setLastPushScreens([]);
        }

        $this->doctrine->getManager()->flush();
    }

    /**
     * @When I print all channel screen regions
     */
    public function iPrintAllChannelScreenRegions()
    {
        $csrs = $this->doctrine->getRepository(
            'Os2DisplayCoreBundle:ChannelScreenRegion'
        )->findAll();
        var_dump($csrs);
    }

    /**
     * @When I add channel screen region with channel :arg1 screen :arg2 region :arg3
     */
    public function iAddChannelScreenRegionWithChannelScreenRegion(
        $arg1,
        $arg2,
        $arg3
    ) {
        $screenRepository = $this->doctrine->getManager()->getRepository(
            Screen::class
        );
        $channelRepository = $this->doctrine->getManager()->getRepository(
            Channel::class
        );

        $channel = $channelRepository->findOneBy(['id' => $arg1]);
        $screen = $screenRepository->findOneBy(['id' => $arg2]);

        $csr = new ChannelScreenRegion();
        $csr->setChannel($channel);
        $csr->setScreen($screen);
        $csr->setRegion($arg3);
        $this->doctrine->getManager()->persist($csr);

        $channel->addChannelScreenRegion($csr);
        $screen->addChannelScreenRegion($csr);

        $this->doctrine->getManager()->flush();
    }

    /**
     * @When I create a shared channel with content uniqueId :arg1 index :arg2 screens :arg3
     */
    public function iCreateASharedChannelWithContentUniqueidIndexChannels(
        $arg1,
        $arg2,
        $arg3
    ) {
        $screenRepository = $this->doctrine->getManager()->getRepository(
            Screen::class
        );

        $sharedChannel = new SharedChannel();
        $sharedChannel->setCreatedAt(0);
        $sharedChannel->setModifiedAt(0);
        $sharedChannel->setUniqueId($arg1);
        $sharedChannel->setIndex($arg2);
        $sharedChannel->setContent("{\"id\":17,\"title\":\"Delt\",\"slides\":[{\"template\":\"slideshow\",\"options\":{\"duration\":10,\"animation\":\"\",\"fade\":\"fade\",\"logopos\":{},\"logosize\":\"10%\"},\"id\":34,\"title\":\"sdafasdf\",\"orientation\":\"landscape\",\"created_at\":1518180468,\"duration\":15,\"published\":true,\"media_type\":\"image\",\"slide_type\":\"slideshow\",\"media\":[],\"media_thumbs\":[],\"logo\":\"\",\"preview_path\":\"\",\"template_path\":\"\",\"path\":\"\",\"css_path\":\"\",\"js_path\":\"\",\"js_script_id\":\"slideshow\",\"server_path\":\"https:\/\/admin.os2display.vm\"}],\"unique_id\":\"" . $arg1 ."\",\"created_at\":1518180483,\"schedule_repeat_days\":[],\"_id\":\"" . $arg1 ."\",\"_score\":1}");

        $screens = json_decode($arg3);

        foreach ($screens as $screenId) {
            $screen = $screenRepository->findOneBy(['id' => $screenId]);

            $channelScreenRegion = new ChannelScreenRegion();
            $channelScreenRegion->setScreen($screen);
            $channelScreenRegion->setSharedChannel($sharedChannel);
            $channelScreenRegion->setRegion(0);

            $this->doctrine->getManager()->persist($channelScreenRegion);

            $sharedChannel->addChannelScreenRegion($channelScreenRegion);
        }

        $this->doctrine->getManager()->persist($sharedChannel);

        $this->doctrine->getManager()->flush();
    }
}
