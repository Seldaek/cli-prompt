<?php

/*
 * This file is part of CLI Prompt.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Seld\CliPrompt;

class CliPrompt
{
    /**
     * Prompts the user for input and shows what they type
     *
     * @return string
     */
    public static function prompt()
    {
        $stdin = fopen('php://stdin', 'r');
        $answer = self::trimAnswer(fgets($stdin, 4096));
        fclose($stdin);

        return $answer;
    }

    /**
     * Prompts the user for input and hides what they type
     *
     * @param  bool   $allowFallback If prompting fails for any reason and this is set to true the prompt
     *                               will be done using the regular prompt() function, otherwise a
     *                               \RuntimeException is thrown.
     * @return string
     * @throws RuntimeException on failure to prompt, unless $allowFallback is true
     */
    public static function hiddenPrompt($allowFallback = false)
    {
        // handle windows
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            // fallback to hiddeninput executable
            $exe = __DIR__.'\\..\\res\\hiddeninput.exe';

            // handle code running from a phar
            if ('phar:' === substr(__FILE__, 0, 5)) {
                $tmpExe = sys_get_temp_dir().'/hiddeninput.exe';

                // use stream_copy_to_stream instead of copy
                // to work around https://bugs.php.net/bug.php?id=64634
                $source = fopen($exe, 'r');
                $target = fopen($tmpExe, 'w+');
                stream_copy_to_stream($source, $target);
                fclose($source);
                fclose($target);
                unset($source, $target);

                $exe = $tmpExe;
            }

            $answer = self::trimAnswer(shell_exec($exe));

            // clean up
            if (isset($tmpExe)) {
                unlink($tmpExe);
            }

            // output a newline to be on par with the regular prompt()
            echo PHP_EOL;

            return $answer;
        }

        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (array('bash', 'zsh', 'ksh', 'csh', 'sh') as $sh) {
                if ('OK' === rtrim(shell_exec(sprintf($test, $sh)))) {
                    $shell = $sh;
                    break;
                }
            }

            if (isset($shell)) {
                $readCmd = ($shell === 'csh') ? 'set mypassword = $<' : 'read -r mypassword';
                $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
                $value = self::trimAnswer(shell_exec($command));

                // output a newline to be on par with the regular prompt()
                echo PHP_EOL;

                return $value;
            }
        }

        // not able to hide the answer
        if (!$allowFallback) {
            throw new \RuntimeException('Could not prompt for input in a secure fashion, aborting');
        }

        return self::prompt();
    }

    private static function trimAnswer($str)
    {
        return preg_replace('{\r?\n$}D', '', $str);
    }
}
