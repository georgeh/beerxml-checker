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

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig');
});

$app->post('/upload', function(\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    /** @var Symfony\Component\HttpFoundation\File\UploadedFile $beerXmlFile */
    $beerXmlFile = $request->files->get('beerxml');
    $parser = new BeerXML\Parser();
    $recipes = $parser->parse(file_get_contents($beerXmlFile->getRealPath()));
    return $app['twig']->render('upload.twig', array('recipes' => $recipes));
});


$app->run();