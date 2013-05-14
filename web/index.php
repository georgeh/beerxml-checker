<?php
// Serve up static files for the built in web server
$filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../twig',
));

$app->register(new Silex\Provider\SessionServiceProvider());

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig');
});

$app->post('/upload', function(\Symfony\Component\HttpFoundation\Request $request) use ($app) {
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
    return $app['twig']->render('show.twig', array('recipes' => $recipes));
});


$app->run();