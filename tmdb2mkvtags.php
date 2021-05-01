#!/usr/bin/env php
<?php
/**
 * Generate a Matroska tags file from TMDb information
 *
 * @link https://www.themoviedb.org/
 * @link https://www.matroska.org/technical/tagging.html
 * @link https://developers.themoviedb.org/3/
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 */
if ($argc < 3) {
    fwrite(STDERR, "Usage: tmdb2mkvtags.php LANGUAGE \"MOVIE TITLE\" [OUTDIR]\n");
    exit(1);
}

$apiToken = null;
$language = $argv[1];
$title    = $argv[2];
$outdir  = null;
if ($argc == 4) {
    $outdir = $argv[3];
}


$configFiles = [];
$configFiles[] = preg_replace('#.php$#', '', $argv[0]) . '.config.php';
if (isset($_SERVER['XDG_CONFIG_HOME'])) {
    $configFiles[] = $_SERVER['XDG_CONFIG_HOME'] . '/tmdb2mkvtags.config.php';
} else if (isset($_SERVER['HOME'])) {
    $configFiles[] = $_SERVER['HOME'] . '/.config/tmdb2mkvtags.config.php';
}
$configFiles[] = '/etc/tmdb2mkvtags.config.php';
foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        require_once $configFile;
        break;
    }
}
if ($apiToken === null) {
    fwrite(STDERR, "API token is not set\n");
    fwrite(STDERR, "Configuration files tried:\n " . implode("\n ", $configFiles) . "\n");
    exit(2);
}


$movies = queryTmdb(
    '/3/search/movie'
    . '?query=' . urlencode($title)
    . '&language=' . urlencode($language)
    . '&include_adult=1'
);

if ($movies->total_results == 0) {
    fwrite(STDERR, "No movies found\n");
    exit(20);

} else if ($movies->total_results == 1) {
    $movie = $movies->results[0];

} else {
    $page = 1;
    $itemsPerPage = 0;
    do {
        fwrite(STDERR, sprintf("Found %d movies\n", $movies->total_results));
        foreach ($movies->results as $key => $movie) {
            fwrite(STDERR, sprintf("[%2d] %s\n", $key + ($page -1) * $itemsPerPage, $movie->title));
        }
        if ($page > 1) {
            fwrite(STDERR, "p: previous page\n");
        }
        if ($movies->total_pages > $page) {
            $itemsPerPage = count($movies->results);
            fwrite(STDERR, "n: next page\n");
        }
        fwrite(STDERR, "\n");
        fwrite(STDERR, 'Your selection: ');
        $cmd = readline();
        if (is_numeric($cmd)) {
            $num = $cmd - ($page - 1) * $itemsPerPage;
            if (isset($movies->results[$num])) {
                $movie = $movies->results[$num];
                break;
            }
            fwrite(STDERR, "Invalid selection $num\n");
        } else if ($cmd == 'n' && $movies->total_pages > $page) {
            $page++;
        } else if ($cmd == 'p' && $page > 1) {
            $page--;
        } else if ($cmd == 'q' || $cmd == 'quit' || $cmd == 'exit') {
            exit(30);
        }

        $movies = queryTmdb(
            '/3/search/movie'
            . '?query=' . urlencode($title)
            . '&language=' . urlencode($language)
            . '&include_adult=1'
            . '&page=' . $page
        );
    } while (true);
}


$details = queryTmdb('3/movie/' . $movie->id . '?language=' . $language);
$credits = queryTmdb('3/movie/' . $movie->id . '/credits?language=' . $language);

$downloadImages = true;

$xml = new MkvTagXMLWriter();
if ($outdir === '-') {
    $xml->openMemory();
    $downloadImages = false;
    fwrite(STDERR, "Not downloading images\n");
} else {
    if ($outdir === null) {
        $outdir = trim(str_replace('/', ' ', $movie->title));
    }
    $outdir = rtrim($outdir, '/') . '/';
    if (is_file($outdir)) {
        fwrite(STDERR, "Error: Output directory is a file\n");
        exit(2);
    }
    if (!is_dir($outdir)) {
        mkdir($outdir);
    }
    $outfile = $outdir . 'mkvtags.xml';
    $xml->openURI($outfile);
}

$xml->setIndent(true);
$xml->startDocument("1.0");
$xml->writeRaw("<!DOCTYPE Tags SYSTEM \"matroskatags.dtd\">\n");

$xml->startElement("Tags");

if ($details->belongs_to_collection) {
    $xml->startComment();
    $xml->text('Collection information');
    $xml->endComment();

    $xml->startElement("Tag");
    $xml->targetType(70);
    $xml->simple('TITLE', $details->belongs_to_collection->name, $language);
    $xml->endElement();
}


$xml->startComment();
$xml->text('Movie information');
$xml->endComment();

$xml->startElement("Tag");

$xml->targetType(50);
$xml->simple('TITLE', $movie->title, $language);
if ($details->tagline) {
    $xml->simple('SUBTITLE', $details->tagline, $language);
}
$xml->simple('SYNOPSIS', $movie->overview, $language);

$xml->simple('DATE_RELEASED', $movie->release_date);

foreach ($details->genres as $genre) {
    $xml->simple('GENRE', $genre->name, $language);
}

$xml->simple('RATING', $movie->vote_average / 2);//0-10 on TMDB, 0-5 mkv
$xml->simple('TMDB', 'movie/' . $movie->id);
$xml->simple('IMDB', $details->imdb_id);

if ($language != $movie->original_language) {
    $xml->startElement('Simple');
    $xml->startElement('Name');
    $xml->text('ORIGINAL');
    $xml->endElement();
    $xml->simple('TITLE', $movie->original_title, $movie->original_language);
    $xml->endElement();//Simple
}


foreach ($credits->cast as $actor) {
    $xml->actor($actor->name, $actor->character, $language);
}

//map tmdb job to matroska tags
$crewMap = [
    'Art Direction'           => 'ART_DIRECTOR',
    'Costume Design'          => 'COSTUME_DESIGNER',
    'Director of Photography' => 'DIRECTOR_OF_PHOTOGRAPHY',
    'Director'                => 'DIRECTOR',
    'Editor'                  => 'EDITED_BY',
    'Novel'                   => 'WRITTEN_BY',
    'Original Music Composer' => 'COMPOSER',
    'Producer'                => 'PRODUCER',
    'Screenplay'              => 'WRITTEN_BY',
    'Sound'                   => 'COMPOSER',
    'Theme Song Performance'  => 'LEAD_PERFORMER',
];
foreach ($credits->crew as $crewmate) {
    if (isset($crewMap[$crewmate->job])) {
        $xml->simple($crewMap[$crewmate->job], $crewmate->name);
    }
}


$xml->endElement();//Tag
$xml->endElement();//Tags
$xml->endDocument();

if ($outdir === null) {
    echo $xml->outputMemory();
} else {
    $xml->flush();
}


if ($downloadImages) {
    //we take the largest scaled image, not the original image
    $tmdbConfig = queryTmdb('3/configuration');
    foreach ($tmdbConfig->images as $key => $sizes) {
        if (is_array($sizes)) {
            foreach ($sizes as $sizeKey => $value) {
                if ($value == 'original') {
                    unset($tmdbConfig->images->$key[$sizeKey]);
                }
            }
        }
    }

    if ($details->poster_path) {
        $size = $tmdbConfig->images->poster_sizes[
            array_key_last($tmdbConfig->images->poster_sizes)
        ];
        $url = $tmdbConfig->images->secure_base_url . $size . $details->poster_path;
        $imagePath = $outdir
            . 'cover.' . pathinfo($details->poster_path, PATHINFO_EXTENSION);
        if (!file_exists($imagePath)) {
            file_put_contents($imagePath, file_get_contents($url));
        }
    }

    if ($details->backdrop_path) {
        $size = $tmdbConfig->images->backdrop_sizes[
            array_key_last($tmdbConfig->images->backdrop_sizes)
        ];
        $url = $tmdbConfig->images->secure_base_url . $size . $details->backdrop_path;
        $imagePath = $outdir
            . 'backdrop.' . pathinfo($details->poster_path, PATHINFO_EXTENSION);
        if (!file_exists($imagePath)) {
            file_put_contents($imagePath, file_get_contents($url));
        }
    }
}


if ($outdir !== '-') {
    $fulldir = realpath($outdir);
    fwrite(STDERR, "Files written into directory:\n$fulldir\n");
}



//var_dump($credits);
//var_dump($movie, $details);


function queryTmdb($path)
{
    global $apiToken;

    $url = 'https://api.themoviedb.org/' . $path;
    $ctx = stream_context_create(
        [
            'http' => [
                'timeout'       => 5,
                'ignore_errors' => true,
                'header'        => 'Authorization: Bearer ' . $apiToken
            ]
        ]
    );
    $res = file_get_contents($url, false, $ctx);
    list(, $statusCode) = explode(' ', $http_response_header[0]);
    $data = json_decode($res);

    if ($statusCode != 200) {
        if (isset($data->status_code) && isset($data->status_message)) {
            throw new Exception(
                'API error: ' . $data->status_code . ' ' . $data->status_message
            );
        }
        throw new Exception('Error querying API: ' . $statusCode, $statusCode);
    }
    return $data;
}

class MkvTagXMLWriter extends XMLWriter
{
    public function actor($actorName, $characterName)
    {
        $this->startElement('Simple');
        $this->startElement('Name');
        $this->text('ACTOR');
        $this->endElement();
        $this->startElement('String');
        $this->text($actorName);
        $this->endElement();
        $this->simple('CHARACTER', $characterName);
        $this->endElement();//Simple
    }

    public function simple($key, $value, $language = null)
    {
        $this->startElement('Simple');
        $this->startElement('Name');
        $this->text($key);
        $this->endElement();
        $this->startElement('String');
        $this->text($value);
        $this->endElement();
        if ($language) {
            $this->startElement('TagLanguage');
            $this->text($language);
            $this->endElement();
        }
        $this->endElement();
    }

    public function targetType($value)
    {
        $this->startElement('Targets');
        $this->startElement('TargetType');
        $this->text($value);
        $this->endElement();
        $this->endElement();
    }
}
?>
