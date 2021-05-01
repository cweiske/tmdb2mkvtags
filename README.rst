============
tmdb2mkvtags
============

Generate a Matroska video tags file from TMDb__ information.
Also downloads the cover (poster) and backdrop images.

__ https://www.themoviedb.org/


Usage
=====

1. Copy ``tmdb2mkvtags.config.php.dist`` to ``tmdb2mkvtags.config.php`` and add your API token
   Supported configuration file directories:

   1. Same directory as ``tmdb2mkvtags.php``
   2. ``$home/.config/`` (or ``XDG_CONFIG_HOME``)
   3. ``/etc/``
2. Run tmdb2mkvtags.php::

     php tmdb2mkvtags.php de "James Bond Diamantenfieber"
3. A directory with the movie title "James Bond 007 - Diamantenfieber" has been
   created and contains the following files:

   - ``mkvtags.xml``
   - ``cover.jpg``
   - ``backdrop.jpg``


Example session
---------------
::

    $ ./tmdb2mkvtags.php de "James Bond"
    Found 69 movies
    [ 0] The James Bond Story
    [ 1] 30 Years of James Bond
    [ 2] James Bond - 50th Anniversary: Bonus Features
    [ 3] Noi non siamo come James Bond
    [ 4] Happy Anniversary 007: 25 Years of James Bond
    [ 5] James Bond: For Real
    [ 6] In Search of James Bond with Jonathan Ross
    [ 7] Richard Sorge - Stalins James Bond
    [ 8] Jatt James Bond
    [ 9] L'uomo che uccise James Bond
    [10] Daniel Craig vs James Bond
    [11] Silhouettes: The James Bond Titles
    [12] The World of James Bond
    [13] James Bond In India
    [14] James Bond 777
    [15] James Bond Supports International Women's Day
    [16] James Bond 50th Anniversary Gala Concert
    [17] James Bond: The First 21 Years
    [18] James Bond Down River
    [19] Stierlitz, le James Bond russe
    n: next page

    Your selection: n
    Found 69 movies
    [20] James Bond: In Service of Nothing
    [21] The Guns of James Bond
    [22] The Incredible World of James Bond
    [23] James Bond: License to Thrill
    [24] The Men Behind the Mayhem: The Special Effects of James Bond
    [25] James Bond's Greatest Hits
    [26] James Bond 007 - Casino Royale
    [27] James Bond 007 - Ein Quantum Trost
    [28] James Bond 007 - Skyfall
    [29] James Bond 007 - Der Hauch des Todes
    [30] Casino Royale
    [31] James Bond 007 - Man lebt nur zweimal
    [32] James Bond 007 - In tödlicher Mission
    [33] James Bond 007 - Lizenz zum Töten
    [34] James Bond 007 - Octopussy
    [35] James Bond 007 - Stirb an einem anderen Tag
    [36] James Bond 007 - GoldenEye
    [37] James Bond 007 - Leben und sterben lassen
    [38] James Bond 007 jagt Dr. No
    [39] James Bond 007 - Im Angesicht des Todes
    p: previous page
    n: next page

    Your selection: 30
    Files written into directory:
    /home/cweiske/dev/tmdb2mkvtags/James Bond 007 - Casino Royale

... and the directory ``James Bond 007 - Casino Royale`` is generated, with
``mkvtags.xml`` ready to use with ``mkvmerge``.
