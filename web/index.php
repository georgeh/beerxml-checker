<?php
// Serve up static files for the built in web server
$filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

require_once __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\Request;

Symfony\Component\HttpKernel\Debug\ErrorHandler::register();

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../twig',
));

$app->register(new Silex\Provider\SessionServiceProvider());

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig');
});

$app->post('/upload', function (Request $request) use ($app) {
    /** @var Symfony\Component\HttpFoundation\File\UploadedFile $beerXmlFile */
    $beerXmlFile = $request->files->get('beerxml');
    $xml = file_get_contents($beerXmlFile->getRealPath());
    $app['session']->set('raw_beerxml', $xml);
    $parser = new BeerXML\Parser();
    $recipes = $parser->parse($xml);
    $app['session']->set('recipes', $recipes);
    return $app->redirect('/show');
});

$app->get('/show', function () use ($app) {
    $recipes = $app['session']->get('recipes');
    if (empty($recipes)) {
        return $app->redirect('/');
    }

    return $app['twig']->render('show.twig', array(
        'recipes' => $recipes,
        'beerxml' => $app['session']->get('raw_beerxml'))
    );
});


$app->get('/download', function () use ($app) {
    $recipes = $app['session']->get('recipes');
    if (empty($recipes)) {
        return $app->redirect('/');
    }

    $generator = new BeerXML\Generator();
    foreach ($recipes as $recipe) {
        $generator->addRecord($recipe);
    }
    $xml = $generator->render();

    $headers = array(
        'Content-Type' => 'text/xml',
        'Content-Disposition' => 'attachment; filename=beer.xml'
    );

    return Symfony\Component\HttpFoundation\Response::create($xml, 200, $headers);
});


$app->post('/feedback', function (Request $request) use ($app) {
    $recipes = $app['session']->get('recipes');
    $xml = $app['session']->get('raw_beerxml');
    if (empty($recipes) || empty($xml)) {
        return $app->redirect('/');
    }
    $comments = $request->get('flippitydo');

    $db = new PDO('sqlite:' . __DIR__ . '/../data/feedback.sqlite');
    $insert = $db->prepare('INSERT INTO feedback (beerxml, parsed, comments) VALUES (:beerxml, :parsed, :comments)');
    $insert->execute(array('beerxml' => $xml, 'parsed' => serialize($recipes), 'comments' => $comments));
    return $app['twig']->render('feedback.twig');

});


$app->run();