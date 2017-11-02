# How to use

1. Clone this repository
2. Install libraries via composer
    ```bash
    composer install
    ```
3. Grab issue.
    ```bash
     php grab.php -u http://jurnalu.ru/online-reading/comicsonline/ion2006/ion2006001
    ```
    You may grab entire series with argument `-s`.
    ```bash
     php grab.php -s -u http://jurnalu.ru/online-reading/comicsonline/ion2006/ion2006001
    ```
    Or You may grab a specific number of issues starting with one in the link with argument `-i`.
    ```bash
     php grab.php -s -i 2 -u http://jurnalu.ru/online-reading/comicsonline/ion2006/ion2006001
    ```
