<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/curl.php';
require_once __DIR__ . '/../config.php';

use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use Igorw\Trashbin\Storage;
use Igorw\Trashbin\Validator;
use Igorw\Trashbin\Parser;

use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

use Symfony\Component\Finder\Finder;

use PaZa\Curl\Curl;

$app = new Application();

$app['debug'] = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.options' => array('cache' => __DIR__.'/../cache/twig', 'debug' => true),
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

#$app['buzz'] = new Buzz\Browser(new Buzz\Client\Curl());

$analytics = '';
if (file_exists(__DIR__ . '/analytics.html')) {
    $analytics = file_get_contents(__DIR__ . '/analytics.html');
}
$app["twig"]->addGlobal("analytics", $analytics);

/**
 * Logout
 */
$app->match('/logout', function(Request $request) use($app) {
    $privateToken = $app['session']->set('private_token', NULL);
    return $app->redirect($app['url_generator']->generate('home'));
})
->bind('logout');

/**
 * Home
 */
$app->match('/', function(Request $request) use($app, $cardMakerConfig) {

    $privateToken = $app['session']->get('private_token');

    if (!empty($privateToken)) {
        return $app->redirect($app['url_generator']->generate('project'));
    }

    $privateToken = $request->get('privateToken');
    $gitlabPath = $request->get('gitlabPath');

    if (!empty($privateToken) && !empty($gitlabPath)) {
        
        $url      = vsprintf('%s/projects?private_token=%s&per_page=1', array($gitlabPath, $privateToken));
        $response = json_decode(@file_get_contents($url));

        $app['session']->set('private_token', $privateToken);
        $app['session']->set('gitlab_path', $gitlabPath);

        if (!is_null($response)) {
            return $app->redirect($app['url_generator']->generate('project'));
        }
    }

    if (empty($cardMakerConfig['apiPath'])) {
        $cardMakerConfig['apiPath'] = '';
    }
    if (empty($gitlabPath)) {
        $gitlabPath = $cardMakerConfig['apiPath'];
    }

    return $app['twig']->render('front.html.twig', array(
        'privateToken'  => $privateToken,
        'gitlabPath'    => $gitlabPath,
    ));
})
->bind('home');

/**
 * Project selection page
 */
$app->get('/project', function(Request $request) use($app, $cardMakerConfig) {

    $privateToken = $app['session']->get('private_token');
    $gitlabPath = $app['session']->get('gitlab_path');

    if (empty($privateToken) || empty($gitlabPath)) {
        return $app->redirect($app['url_generator']->generate('home'));
    }

    $url      = vsprintf('%s/projects?private_token=%s&per_page=100', array($gitlabPath, $privateToken));
    $projects = json_decode(@file_get_contents($url));

    return $app['twig']->render('project.html.twig', array(
        'projects' => $projects,
    ));
})
->bind('project');

/**
 * Cards settings
 */
$app->get('/cards', function(Request $request) use($app, $cardMakerConfig) {

    $privateToken = $app['session']->get('private_token');
    $gitlabPath = $app['session']->get('gitlab_path');

    if (empty($privateToken) || empty($gitlabPath)) {
        return $app->redirect($app['url_generator']->generate('home'));
    }

    if (!$projectId = $request->query->get('projectId')) {
        return $app->redirect($app['url_generator']->generate('project'));
    }

    $url        = vsprintf('%s/projects/%d/milestones?private_token=%s&per_page=100', array($gitlabPath, $projectId, $privateToken));
    $milestones = json_decode(file_get_contents($url));

    return $app['twig']->render('cards.html.twig', array(
        'projectId'     => $projectId,
        'milestones'    => $milestones,
    ));
})
->bind('cards');

/**
 * Cards
 */
$app->get('/cards.pdf', function(Request $request) use($app, $cardMakerConfig) {

    $privateToken = $app['session']->get('private_token');
    $gitlabPath = $app['session']->get('gitlab_path');

    if (empty($privateToken) || empty($gitlabPath)) {
        return $app->redirect($app['url_generator']->generate('home'));
    }

    if (!$projectId = $request->query->get('projectId')) {
        return $app->redirect($app['url_generator']->generate('project'));
    }

    $showNumbers        = $request->query->get('showNumbers', false);
    $cropmarks          = $request->query->get('cropmarks', false);
    $openOnly           = $request->query->get('openonly', false);
    $cardsPerPage       = $request->query->get('cardsPerPage', 8);
    $alignment          = $request->query->get('alignment', 'C');
    $milestones         = $request->query->get('milestones', array());
    $titleMaxLength     = $request->query->get('titleMaxLength', 0);

    $url                = vsprintf('%s/projects/%d/issues?private_token=%s&per_page=100', array($gitlabPath, $projectId, $privateToken));
    $issues             = json_decode(file_get_contents($url));
    $issuesClean        = array();

    foreach($issues as $issue) {
        // Available attributes are:
        // - id
        // - project_id
        // - title
        // - description
        // - labels
        // - milestone
        // - assignee
        // - author
        // - closed
        // - updated_at
        // - created_at

        if (!isset($issue->closed)) {
            $issue->closed = false;
        }

        // skip closed if open only should be displayed
        if ($openOnly && true === $issue->closed) {
            continue;
        }

        if (!empty($milestones) && !in_array($issue->milestone->id, $milestones)) {
            continue;
        }

        $issue->titleSliced = html_entity_decode($issue->title);
        if (0 < (int) $titleMaxLength) {
            $issue->titleSliced = truncate($issue->title, (int) $titleMaxLength);
        }

        // add to list
        $issuesClean[] = $app['twig']->render('issue.html.twig', array(
            'issue'     => $issue,
        ));
    }

    $issuesCleanText = implode("\n---\n", $issuesClean);

    $curl = new Curl($cardMakerConfig['cardmakerPath']);

    $curl->setPostParams(array(
        'cards'         => $issuesCleanText,
        'cardsPerPage'  => $cardsPerPage,
        'showNumbers'   => (!empty($showNumbers) ? 1 : 0),
        'cropmark'      => (!empty($cropmarks) ? 1 : 0),
        'alignment'     => $alignment,
        'html'          => true,
    ));

    header('Content-type: application/pdf');
    header('Content-Disposition: attachment; filename="cards.pdf"');
    echo $curl->getContent();
    exit();
})
->bind('cards.pdf');

return $app;

function truncate($text, $length = 140) {
    if(strlen($text) > $length) {
        $text = substr($text, 0, strrpos($text,' ', $length - strlen($text)-3)) . '...';
    }
    return $text;
}