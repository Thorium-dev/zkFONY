<?php

namespace AppBundle\Service;

use Symfony\Component\Config\Definition\Exception\Exception;

class File
{
    private $container;
    private $rootDir;
    private $error;

    private $createByParameters;

    private $createDirByParameters;
    private $removeDirByParameters;

    // error success
    private $status;

    private $DS = DIRECTORY_SEPARATOR;

    public function __construct($container)
    {

        $this->createByParameters = array(
            'string' => function($dir){
                $dir = $this->rootDir . '' . trim($dir, $this->DS);
                mkdir($dir);
                return;
            }
        );

        $this->createDirByParameters = array(
            'string' => function($dir){
                $dir = $this->rootDir . '' . trim($dir, $this->DS);
                mkdir($dir);
                return;
            }
        );

        $this->removeDirByParameters = array(
            'string' => function($dir){
                foreach(array_diff(scandir($dir), array('.', '..')) as $file) {
//                    if ('.' === $file || '..' === $file){continue;}
                    if (is_dir("$dir" . $this->DS . "$file")){$this->removeDirByParameters['string']("$dir" . $this->DS . "$file");}
                    else{unlink("$dir" . $this->DS . "$file");}
                }
                rmdir($dir);
                return;
            }
        );

        $this->minifyByParameters = array(
            'js' => function($input){
                if(!$input = trim($input)) return $input;
                // Create chunk(s) of string(s), comment(s), regex(es) and text
                $SS = '"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'';
                $CC = '\/\*[\s\S]*?\*\/';
                $input = preg_split('#(' . $SS . '|' . $CC . '|\/[^\n]+?\/(?=[.,;]|[gimuy]|$))#', $input, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                $output = "";
                foreach($input as $v) {
                    if(trim($v) === "") continue;
                    if(
                        ($v[0] === '"' && substr($v, -1) === '"') ||
                        ($v[0] === "'" && substr($v, -1) === "'") ||
                        ($v[0] === $this->DS && substr($v, -1) === $this->DS)
                    ) {
                        // Remove if not detected as important comment ...
                        if(strpos($v, '//') === 0 || (strpos($v, '/*') === 0 && strpos($v, '/*!') !== 0 && strpos($v, '/*@cc_on') !== 0)) continue;
                        $output .= $v; // String, comment or regex ...
                    } else {
                        $output .= preg_replace(
                            array(
                                // Remove inline comment(s) [^1]
                                '#\s*\/\/.*$#m',
                                // Remove white-space(s) around punctuation(s) [^2]
                                '#\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#',
                                // Remove the last semi-colon and comma [^3]
                                '#[;,]([\]\}])#',
                                // Replace `true` with `!0` and `false` with `!1` [^4]
                                '#\btrue\b#', '#\bfalse\b#', '#\breturn\s+#'
                            ),
                            array(
                                // [^1]
                                "",
                                // [^2]
                                '$1',
                                // [^3]
                                '$1',
                                // [^4]
                                '!0', '!1', 'return '
                            ),
                            $v
                        );
                    }
                }
                return preg_replace(
                    array(
                        // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}` [^1]
                        '#(' . $CC . ')|([\{,])([\'])(\d+|[a-z_]\w*)\3(?=:)#i',
                        // From `foo['bar']` to `foo.bar` [^2]
                        '#([\w\)\]])\[([\'"])([a-z_]\w*)\2\]#i'
                    ),
                    array(
                        // [^1]
                        '$1$2$4',
                        // [^2]
                        '$1.$3'
                    ),
                    $output
                );
            },
            'css' => function($input){
                function _minify_css($input) {
                    // Keep important white-space(s) in `calc()`
                    if(stripos($input, 'calc(') !== false) {
                        $input = preg_replace_callback('#\b(calc\()\s*(.*?)\s*\)#i', function($m) {
                            return $m[1] . preg_replace('#\s+#', "\x1A" . '\s', $m[2]) . ')';
                        }, $input);
                    }
                    // Minify ...
                    return preg_replace(
                        array(
                            // Fix case for `#foo [bar="baz"]` and `#foo :first-child` [^1]
                            '#(?<![,\{\}])\s+(\[|:\w)#',
                            // Fix case for `[bar="baz"] .foo` and `@media (foo: bar) and (baz: qux)` [^2]
                            '#\]\s+#', '#\b\s+\(#', '#\)\s+\b#',
                            // Minify HEX color code ... [^3]
                            '#\#([\da-f])\1([\da-f])\2([\da-f])\3\b#i',
                            // Remove white-space(s) around punctuation(s) [^4]
                            '#\s*([~!@*\(\)+=\{\}\[\]:;,>\/])\s*#',
                            // Replace zero unit(s) with `0` [^5]
                            '#\b(?:0\.)?0([a-z]+\b|%)#i',
                            // Replace `0.6` with `.6` [^6]
                            '#\b0+\.(\d+)#',
                            // Replace `:0 0`, `:0 0 0` and `:0 0 0 0` with `:0` [^7]
                            '#:(0\s+){0,3}0(?=[!,;\)\}]|$)#',
                            // Replace `background(?:-position)?:(0|none)` with `background$1:0 0` [^8]
                            '#\b(background(?:-position)?):(0|none)\b#i',
                            // Replace `(border(?:-radius)?|outline):none` with `$1:0` [^9]
                            '#\b(border(?:-radius)?|outline):none\b#i',
                            // Remove empty selector(s) [^10]
                            '#(^|[\{\}])(?:[^\{\}]+)\{\}#',
                            // Remove the last semi-colon and replace multiple semi-colon(s) with a semi-colon [^11]
                            '#;+([;\}])#',
                            // Replace multiple white-space(s) with a space [^12]
                            '#\s+#'
                        ),
                        array(
                            // [^1]
                            "\x1A" . '\s$1',
                            // [^2]
                            ']' . "\x1A" . '\s', "\x1A" . '\s(', ')' . "\x1A" . '\s',
                            // [^3]
                            '#$1$2$3',
                            // [^4]
                            '$1',
                            // [^5]
                            '0',
                            // [^6]
                            '.$1',
                            // [^7]
                            ':0',
                            // [^8]
                            '$1:0 0',
                            // [^9]
                            '$1:0',
                            // [^10]
                            '$1',
                            // [^11]
                            '$1',
                            // [^12]
                            ' '
                        ),
                        $input
                    );
                }
                if( ! $input = trim($input)) return $input;
                $SS = '"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'';
                $CC = '\/\*[\s\S]*?\*\/';
                // Keep important white-space(s) between comment(s)
                $input = preg_replace('#(' . $CC . ')\s+(' . $CC . ')#', '$1' . "\x1A" . '\s$2', $input);
                // Create chunk(s) of string(s), comment(s) and text
                $input = preg_split('#(' . $SS . '|' . $CC . ')#', $input, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                $output = "";
                foreach($input as $v) {
                    if(trim($v) === "") continue;
                    if(
                        ($v[0] === '"' && substr($v, -1) === '"') ||
                        ($v[0] === "'" && substr($v, -1) === "'") ||
                        (strpos($v, '/*') === 0 && substr($v, -2) === '*/')
                    ) {
                        // Remove if not detected as important comment ...
                        if($v[0] === $this->DS && strpos($v, '/*!') !== 0) continue;
                        $output .= $v; // String or comment ...
                    } else {
                        $output .= _minify_css($v);
                    }
                }
                // Remove quote(s) where possible ...
                $output = preg_replace(
                    array(
                        // '#(' . $CC . ')|(?<!\bcontent\:|[\s\(])([\'"])([a-z_][-\w]*?)\2#i',
                        '#(' . $CC . ')|\b(url\()([\'"])([^\s]+?)\3(\))#i'
                    ),
                    array(
                        // '$1$3',
                        '$1$2$4$5'
                    ),
                    $output);
                return str_replace(array("\x1A" . '\n', "\x1A" . '\t', "\x1A" . '\s'), array("\n", "\t", ' '), $output);
            }
        );

        // Set container and rootDir
        if(!empty($container)){
            $this->container = $container;
            $this->rootDir = $container->getParameter('kernel.root_dir') . $this->DS . ".." . $this->DS;
        }

    }

    /**
     * Get base root directory
     *
     * @return string
     */
    public function getRoot(){
        return $this->rootDir;
    }

    /**
     * Check if a file exists
     *
     * @param $filename
     * @param $dir
     * @return boolean
     */
    public function has($filename, $dir = null){
        $dir = $this->rootDir . '' . trim($dir, $this->DS) . $this->DS;
        return file_exists($dir . '' . trim($filename, $this->DS));
    }

    /**
     * Create a new file
     * Utilise le mode 'w+'
     * Ouvre en lecture et écriture ; place le pointeur de fichier au début du fichier et réduit la taille du fichier à 0. Si le fichier n'existe pas, on tente de le créer.
     *
     * @param $filename
     * @param $content
     * @return $this
     */
    public function create($filename, $content = null){
        $filename = $this->rootDir . $filename;
        $handle = fopen($filename, "w+");
        fwrite($handle, $content);
        fclose($handle);
        return $this;
    }

    /**
     * Remove a file
     *
     * @param $filename
     * @return $this
     */
    public function remove($filename){
        $filename = $this->rootDir . $filename;
        unlink($filename);
        return $this;
    }

    /**
     * delete a file
     *
     * @param $filename
     * @return $this
     */
    public function delete($filename){
        return $this->remove($filename);
    }

    /**
     * Rename a file
     *
     * @param $oldName
     * @param $newName
     * @return $this
     */
    public function rename($oldName, $newName){
        $oldName = $this->rootDir . '' . trim($oldName, $this->DS);
        $newName = $this->rootDir . '' . trim($newName, $this->DS);
        rename($oldName, $newName);
        return $this;
    }

    /**
     * Get files
     *
     * @param $filter
     * @param $dir
     * @return array
     */
    public function get($dir = null, $filter = null){
        $res = array();
        $dir = $this->rootDir . trim($dir, $this->DS) . $this->DS;
        if(is_dir($dir)){
            foreach(array_diff(scandir($dir), array('.', '..')) as $file) {
                if(is_file($dir . $file)){
                    if(!empty($filter)){
                        if(is_string($filter) && preg_match($filter, $file)){
                            $res[] = $file;
                        }
                    }else{
                        $res[] = $file;
                    }
                }
            }
        }
        return $res;
    }

    /**
     * Write into file
     * Utilise le mode 'a+'
     * Ouvre en écriture seule ; place le pointeur de fichier à la fin du fichier. Si le fichier n'existe pas, on tente de le créer. Dans ce mode, la fonction fseek() n'a aucun effet, les écritures surviennent toujours.
     *
     * @param $filename
     * @param $content
     * @return $this
     */
    public function write($filename, $content = null){
        $filename = $this->rootDir . $filename;
        $handle = fopen($filename, 'a+');
        fwrite($handle, $content);
        fclose($handle);
        return $this;
    }

    /**
     * Read file content
     *
     * @param $filename
     * @return $this
     */
    public function read($filename){
        $filename = $this->rootDir . $filename;
        return file_get_contents($filename);
    }

    /**
     * Read file content and put lines in array
     *
     * @param $filename
     * @return $this
     */
    public function readLines($filename){
        $filename = $this->rootDir . $filename;
        return file($filename);
    }

    /**
     * Minify data
     *
     * @param $options
     *      - content : Content to minify
     *      - type : Output type
     *      - file : Get data from file
     *      - output : Output file
     * @return string Minified content
     */
    public function minify($options = null){
        $content = '';
        $type = 'js';
        if(is_string($options) && $this->has($options)){
            $content = $this->read($options);
            $type = pathinfo($options)['extension'];
        }else{
            if(is_string($options)){ $options = ['content' => $options]; }
            if(!is_array($options)){ $options = ['content' => '']; }
            if(isset($options['content'])){ $content = $options['content']; }
            if(isset($options['type'])){ $type = $options['type']; }
            if (isset($options['file'])) {
                $file = $options['file'];
                if(is_string($file) && $this->has($file)){
                    $content = $this->read($options['file']);
                    $type = pathinfo($options['file'])['extension'];
                }
            }
        }
        $functions = $this->minifyByParameters;
        if(isset($functions[$type])){
            $content = $functions[$type]($content);
        }
        if (isset($options['output'])) {
            $this->create($options['output'], $content);
        }
        return $content;
    }

    /**
     * Create new directory
     *
     * @param $dir
     * @return $this
     */
    public function createDir($dir){
        $functions = $this->createDirByParameters;
        $type = gettype($dir);
        if(isset($functions[$type])){
            $functions[$type]($dir);
        }
        return $this;
    }

    /**
     * Remove directory
     *
     * @param $dir
     * @return $this
     */
    public function removeDir($dir){
        $functions = $this->removeDirByParameters;
        $type = gettype($dir);
        if(isset($functions[$type])){
            $functions[$type]($this->rootDir . '' . trim($dir, $this->DS));
        }
        return $this;
    }

    /**
     * Rename a directory
     *
     * @param $oldDir
     * @param $newDir
     * @return $this
     */
    public function renameDir($oldDir, $newDir){
        $oldDir = $this->rootDir . '' . trim($oldDir, $this->DS);
        $newDir = $this->rootDir . '' . trim($newDir, $this->DS);
        if(file_exists($oldDir) && !file_exists($newDir)){
            rename($oldDir, $newDir);
        }
        return $this;
    }

    /**
     * Copy a directory
     *
     * @param $from
     * @param $to
     * @return $this
     */
    public function copyDir($from, $to){
        $from = trim($from, $this->DS);
        $to = trim($to, $this->DS);
        if(is_dir($this->rootDir . '' . $from)){
            $dir = opendir($this->rootDir . '' . $from);
            $this->createDir($to);
            while(($file = readdir($dir)) !== false) {
                if (($file !== '.') && ($file !== '..')) {
                    if (is_dir($this->rootDir . "$from" . $this->DS . "$file")) {
                        $this->createDir($to . "" . $this->DS . "$file");
                        $this->copyDir("$from" . $this->DS . "$file", "$to" . $this->DS . "$file");
                    } else {
                        copy($this->rootDir . "$from" . $this->DS . "$file", $this->rootDir . "$to" . $this->DS . "$file");
                    }
                }
            }
            closedir($dir);
        }
        return $this;
    }

    /**
     * Get directory
     *
     * @param $filter
     * @param $dir
     * @return array
     */
    public function getDir($dir = null, $filter = null){
        $res = array();
        $dir = $this->rootDir . trim($dir, $this->DS) . $this->DS;
        if(is_dir($dir)){
            foreach(array_diff(scandir($dir), array('.', '..')) as $file) {
                if(is_dir($dir . $file)){
                    if(!empty($filter)){
                        if(is_string($filter) && preg_match($filter, $file)){
                            $res[] = $file;
                        }
                    }else{
                        $res[] = $file;
                    }
                }
            }
        }

        return $res;
    }

    /**
     * Download
     *
     * @param $options
     *      - name/output : New file name
     *      - content : Content to download
     *      - file : Get data from file
     *      - description : Download description
     *      - charset : Encoding
     * @return string Content to download
     */
    public function download($options = null){
        $content = "";
        $name = "no-content.txt";
        $description = "File Transfer";
        $charset = "UTF-8";
        if(is_string($options) && $this->has($options)){
            $content = $this->read($options);
            $name = basename($options);
        }else{
            if(is_string($options)){ $options = ['content' => $options]; }
            if(!is_array($options)){ $options = ['content' => '']; }
            if(isset($options['content'])){ $content = $options['content']; }
            if(isset($options['name'])){ $name = $options['name']; }
            if(isset($options['output'])){ $name = $options['output']; }
            if(isset($options['description'])){ $description = $options['description']; }
            if(isset($options['charset'])){ $charset = $options['charset']; }
            if (isset($options['file'])) {
                $file = $options['file'];
                if(is_string($file) && $this->has($file)){
                    $content = $this->read($file);
                }
            }
            $handle = fopen('php://memory', 'a+');
            fwrite($handle, $content);
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);
        }
        if (isset($options['minify'])) {
            $content = $this->minify(array(
                'content' => $content,
                'type' => $options['minify']
            ));
        }
        header("Content-Description: $description");
        header("Content-Type: application/octet-stream;charset=$charset");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Disposition: attachment; filename='" . $name . "'");
        return $content;
    }


}