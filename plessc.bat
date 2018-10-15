@ECHO OFF

REM Replace the following line with the path and file to your PHP executable if necessary
REM You can also set additional options, eg.: to use a specified php.ini, write:
REM "SET PHP=C:\php\php.exe -c C:\php\php-alternative.ini"

SET PHP=php.exe
%PHP% plessc %*
