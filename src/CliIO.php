<?php
namespace KK\CliIO;

class CliIO
{
    /**
     * Log levels
     */
    const LOG_DEFAULT = 0;
    const LOG_WARN    = 1;
    const LOG_ERROR   = 2;
    
    /**
     * Foreground colors map
     * @var array
     */
    private $foregrounds = [
        'none'          => null,
        'black'         => 30,
        'red'           => 31,
        'green'         => 32,
        'yellow'        => 33,
        'blue'          => 34,
        'purple'        => 35,
        'cyan'          => 36,
        'light_gray'    => 37,
        'dark_gray'     => 90,
        'light_red'     => 91,
        'light_green'   => 92,
        'light_yellow'  => 93,
        'light_blue'    => 94,
        'light_magenta' => 95,
        'light_cyan'    => 96,
        'white'         => 97,
    ];
    
    /**
     * Background colors map
     * @var array
     */
    private $backgrounds = [
        'none'          => null,
        'black'         => 40,
        'red'           => 41,
        'green'         => 42,
        'yellow'        => 43,
        'blue'          => 44,
        'purple'        => 45,
        'cyan'          => 46,
        'light_gray'    => 47,
        'dark_gray'     => 100,
        'light_red'     => 101,
        'light_green'   => 102,
        'light_yellow'  => 103,
        'light_blue'    => 104,
        'light_magenta' => 105,
        'light_cyan'    => 106,
        'white'         => 107,
    ];
    
    /**
     * Styles map
     * @var array
     */
    private $styles = [
        'none'      => null,
        'bold'      => 1,
        'faint'     => 2,
        'italic'    => 3,
        'underline' => 4,
        'blink'     => 5,
        'negative'  => 7,
    ];
    
    /**
     * CLI flag
     * @var bool
     */
    private $isCli;
    
    /**
     * Windows flag
     * @var bool
     */
    private $isWindows;
    
    /**
     * POSIX flag
     * @var bool
     */
    private $isPosix;
    
    /**
     * Stream resource to read from
     * @var resource
     */
    private $input;
    
    /**
     * Stream resource to write to
     * @var resource
     */
    private $output;
    
    /**
     * Stream resource to write errors to
     * @var resource
     */
    private $errors;
    
    /**
     * Set streams to work with
     * 
     * @param resource $input
     * @param resource $output
     * @param resource $errors
     */
    public function __construct($input = null, $output = null, $errors = null)
    {
        // Detect environment
        $this->isCli     = $this->isCli();
        $this->isWindows = $this->isWindows();
        
        if (! $input) {
            $stream = $this->isCli ? 'php://stdin' : 'php://input';
            $input = fopen($stream, 'r');
        }
        
        if (! $output) {
            $stream = $this->isCli ? 'php://stdout' : 'php://output';
            $output = fopen($stream, 'w');
        }
        
        if (! $errors) {
            $errors = fopen('php://stderr', 'w');
        }
        
        // register streams
        $this->setInputStream($input);
        $this->setOutputStream($output);
        $this->setErrorsStream($errors);
    }
    
    /**
     * Sets input stream to read from
     * 
     * @param resource $resource
     */
    public function setInputStream($resource)
    {
        if (! is_resource($resource)) {
            throw new Exception('Invalid resource');
        }
        
        $this->input = $resource;
        
        return $this;
    }
    
    /**
     * Sets output stream to write to
     * 
     * @param resource $resource
     */
    public function setOutputStream($resource)
    {
        if (! is_resource($resource)) {
            throw new Exception('Invalid resource');
        }
        
        // Detect posix terminal
        $this->isPosix = $this->isPosix($resource);
        $this->output = $resource;
        
        return $this;
    }
    
    /**
     * Sets errors stream to write to
     * 
     * @param resource $resource
     */
    public function setErrorsStream($resource)
    {
        if (! is_resource($resource)) {
            throw new Exception('Invalid resource');
        }
        
        $this->errors = $resource;
        
        return $this;
    }
    
    /**
     * Reads from the input stream
     * 
     * @return string
     */
    public function read()
    {
        return rtrim(fgets($this->input), PHP_EOL);
    }
    
    /**
     * Writes to output stream
     * 
     * @param  string $message
     * @param  string|null $format
     * @return self
     */
    public function write(string $message, string $format = null)
    {
        // format only for POSIX
        if ($this->isPosix) {
            $message = $this->format($message, $format);
        }
        
        fwrite($this->output, $message);
        return $this;
    }
    
    /**
     * Writes line
     * 
     * @param  string      $message
     * @param  string|null $format
     * @return self
     */
    public function writeLine(string $message, string $format = null)
    {
        return $this->write($message . PHP_EOL, $format);
    }
    
    /**
     * Prompts for user input and reads response
     * 
     * @param  string $message
     * @return string
     */
    public function prompt(string $message, string $format = null)
    {
        $this->write($message, $format);
        return $this->read();
    }
    
    /**
     * Hides cursor and restores on shutdown
     * 
     * @return self
     */
    public function hideCursor()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        // on shutdown
        register_shutdown_function(function () {
            
            // restore hidden
            $this->write("\033[?25h");
        });
        
        // hide
        return $this->write("\033[?25l");
    }
    
    /**
     * Moves cursor up by N rows
     * 
     * @param  int|integer $rows
     * @return self
     */
    public function cursorUp(int $rows = 1)
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[{$rows}A");
    }
    
    /**
     * Moves cursor down by N rows
     * 
     * @param  int|integer $rows
     * @return self
     */
    public function cursorDown(int $rows = 1)
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[{$rows}B");
    }
    
    /**
     * Moves cursor forward by N columns
     * 
     * @param  int|integer $columns
     * @return self
     */
    public function cursorForward(int $columns = 1)
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[{$columns}C");
    }
    
    /**
     * Moves cursor backward by N columns
     * 
     * @param  int|integer $columns
     * @return self
     */
    public function cursorBackward(int $columns = 1)
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[{$columns}D");
    }
    
    /**
     * Erases entire current line
     * 
     * @return self
     */
    public function erase()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[2K");
    }
    
    /**
     * Erases current line from cursor position to start
     * 
     * @return self
     */
    public function eraseToStart()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[1K");
    }
    
    /**
     * Erases current line from cursor position to end
     * 
     * @return self
     */
    public function eraseToEnd()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\033[K");
    }
    
    /**
     * Tab
     * 
     * @return self
     */
    public function tab()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\011");
    }
    
    /**
     * Carriage return
     * 
     * @return self
     */
    public function return()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write("\015");
    }
    
    /**
     * Breaks line
     * 
     * @return self
     */
    public function newLine()
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write(PHP_EOL);
    }
    
    /**
     * Backspaces
     * 
     * @return self
     */
    public function backspace(int $columns = 1)
    {
        if (! $this->isPosix) {
            return $this;
        }
        
        return $this->write(str_repeat("\010", $columns));
    }
    
    /**
     * Logs
     * 
     * @param  string $message
     * @param  int    $level
     * @return self
     */
    public function log(string $message, int $level = self::LOG_DEFAULT)
    {
        $styles = [
            self::LOG_DEFAULT => null,
            self::LOG_WARN    => 'yellow',
            self::LOG_ERROR   => 'white|red|bold',
        ];
        
        $style = $styles[$level] ?? null;
        
        list($usec, $sec) = explode(" ", microtime());
        $usec = str_pad(round($usec * 1000), 3, '0', STR_PAD_RIGHT);
        $time = date("d.m.Y H:i:s.", $sec) . $usec;
        
        return $this
            ->write($time, 'dark_gray')
            ->write(': ')
            ->write($message, $style)
            ->write("\n")
        ;
    }
    
    /**
     * Format string using ANSI escape sequences
     * 
     * @param  string $string
     * @param  string $format defaults to 'none|none|none'
     * @return string
     */
    private function format(string $string, string $format = null)
    {
        if (! $format) {
            return $string;
        }
        
        $format = $format ? explode('|', $format) : [];

        $code = array_filter([
            $this->backgrounds[$format[1] ?? null] ?? null,
            $this->styles[$format[2] ?? null] ?? null,
            $this->foregrounds[$format[0] ?? null] ?? null,
        ]);
                
        $code = implode(';', $code);

        return "\033[" . $code . "m" . $string . "\033[0m";
    }
    
    /**
     * Check if working under CLI
     * 
     * @return bool
     */
    public function isCli()
    {
        return PHP_SAPI == 'cli';
    }
    
    /**
    * Check if working under Windows
    *
    * @see http://stackoverflow.com/questions/738823/possible-values-for-php-os
    * @return bool
    */
    private function isWindows()
    {
        return
            (defined('PHP_OS') && (substr_compare(PHP_OS, 'win', 0, 3, true) === 0)) ||
            (getenv('OS') != false && substr_compare(getenv('OS'), 'windows', 0, 7, true))
        ;
    }
    
    /**
     * Check if a resource is an interactive terminal
     * 
     * @see https://github.com/auraphp/Aura.Cli/blob/2.x/src/Stdio/Handle.php#L117
     * @param  resource  $resource
     * @return bool
     */
    private function isPosix($resource)
    {
        // Windows
        if ($this->isWindows()) {
            return false;
        }
        
        // disable posix errors about unknown resource types
        if (function_exists('posix_isatty')) {
            set_error_handler(function () {});
            $isPosix = posix_isatty($resource);
            restore_error_handler();
            return $isPosix;
        }
        
        return false;
    }
}
