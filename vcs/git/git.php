<?php 

/**
 * git
 */
class GitTool
{
    private $run_path;

    public function __construct($run_path)
    {
        if (! is_dir($run_path)) {
            throw new Exception("执行目录不是文件夹");
        }

        $this->run_path = $run_path;
    }

    public function clone($remote_url, $dir_path, $remote_name = '')
    {
        $command = "git clone {$remote_url} {$dir_path}";
        if ($remote_name) {
            $command .= " -o {$remote_name}";
        }

        return $this->runTermState($command);
    }

    public function branch()
    {
        $command = "git branch";
        $output = $this->runTermOutput($command);

        foreach ($output as $key => $value) {
            $output[$key] = trim(trim($value), '* ');
        }

        return $output;
    }

    public function branchRemote()
    {
        $command = "git branch -r";
        $output = $this->runTermOutput($command);

        $response = [];
        foreach ($output as $key => $value) {
            if (strpos($value, 'HEAD') !== false) continue;

            [$remote_name, $branch] = explode('/', trim($value));

            $response[$remote_name][] = $branch;
        }

        return $response;
    }

    public function checkout($branch, $remote = null)
    {
        $command = "git checkout {$branch}";
        if ($remote) {
            $command = "git checkout -b {$branch} {$remote}";
        }
        //$command .= " 2>/dev/null";

        return $this->runTermState($command);
    }

    public function remote()
    {
        $command = "git remote -v";
        $output = $this->runTermOutput($command);

        $response = [];
        foreach ($output as $key => $value) {
            [$name, $url] = explode("\t", $value);
            $url = str_replace([' (fetch)', ' (push)'], '', $url);

            $response[$name] = $url;
        }

        return $response;
    }

    // 需要高版本 git 才支持
    public function remoteUrl(string $remote_name = 'origin')
    {
        $command = "git remote get-url {$remote_name}";

        $output = $this->runTermOutput($command);

        return $output[0] ?? '';
    }

    public function addRemote($name, $url)
    {
        $command = "git remote add {$name} {$url}";

        return $this->runTermState($command);
    }

    public function pull()
    {
        $command = "git pull";

        return $this->runTermState($command);
    }

    public function push(string $remote_name)
    {
        $command = "git push";

        return $this->runTermState($command);
    }

    protected function runTerm(string $command)
    {
        $dir = $this->getRunDir();
        $commands = [
            "cd {$dir}"
        ];

        $commands[] = $command;

        echo "> {$command}\n";

        exec(join(';', $commands), $output, $return_val);

        return compact('output', 'return_val');
    }
    protected function getRunDir()
    {
        # 返回完整路径
        return realpath($this->run_path);
    }
    protected function runTermState(string $command)
    {
        $runInfo = $this->runTerm($command);

        return !($runInfo['return_val']);
    }
    protected function runTermOutput(string $command)
    {
        $runInfo = $this->runTerm($command);

        return $runInfo['output'];
    }
}

/**
 * manage
 */
class Manage
{
    protected $config_path;

    protected $config;

    public function __construct($config_path)
    {
        $this->config_path = $config_path;
    }

    /**
     * 根据配置文件还原项目
     */
    public function import($config_path, $root_dir = '')
    {
        $config = $this->getConfig($config_path);

        foreach ($config['projects'] as $project) {
            $root_dir = $root_dir ?: $config['root_dir'];
            $gitTool = new GitTool($root_dir.'/'.$project['dir']);

            if ($gitTool->clone()) {
                echo "[success] {$config['root_dir']}/{$project['dir']}";
            } else {
                echo "[fail   ] {$config['root_dir']}/{$project['dir']}";
            }
        }
    }
    protected function getConfig($path = '')
    {
        if (empty($this->config)) {
            if (empty($path)) {
                $path = $this->config_path;
            }

            $config = file_get_contents($path);
            $this->config = json_decode($config, true);
        }

        return $this->config;
    }

    /**
     * 生成配置文件
     */
    public function explode($project_dir, $save_path)
    {
        $root_dir = realpath($project_dir);

        $this->getDirProject($root_dir, $projects);
        foreach ($projects as $k => $project) {
            $projects[$k] = $this->getProjectInfo($project, $root_dir);
        }

        $config = [
            'name' => '',
            'root_dir' => $root_dir,
            'projects' => $projects
        ];

        return $this->saveConfig($config, $save_path);
    }
    protected function saveConfig($config, $save_path)
    {
        $config = json_encode($config, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        if (! is_dir(dirname($save_path))) {
            mkdir(dirname($save_path), 0777);
        }

        return file_put_contents($save_path, $config);
    }
    protected function getDirProject($root_dir, &$project_dir)
    {
        //判断是不是目录
        if(is_dir($root_dir)){
        //如果是目录,则打开目录,返回目录句柄
            $handle = opendir($root_dir);
            //循环从目录句柄中读取
            while (false !== $file = readdir($handle)) {
                //如果读取到".",或".."时,则跳过
                if(in_array($file, [".", "..", "vendor"])) continue;

                // 不是文件夹跳过
                if (!is_dir($root_dir.'/'.$file)) continue;

                if ($file == '.git') {
                    $project_dir[] = $root_dir;
                } else {
                    //判断读到的文件名是不是目录,如果是目录,则开始递归;
                    $this->getDirProject($root_dir.'/'.$file, $project_dir);
                }
            }
            //关闭目录句柄
            closedir($handle);
        }
    }
    protected function getProjectInfo($project_dir, $root_dir)
    {
        $gitTool = new GitTool($project_dir);
        $remote = $gitTool->remote();
        if (empty($remote)) return false;

        $branch = $gitTool->branchRemote();

        $remotes = [];
        foreach ($remote as $name => $url) {
            $remotes[$name] = [
                'url' => $url,
                'branch' => $branch[$name] ?? [],
            ];
        }

        $project_dir = str_replace($root_dir, '', $project_dir);
        $project = [
            'name' => '',
            'dir' => trim($project_dir, '/'),
            'remotes' => $remotes
        ];

        return $project;
    }

    /**
     * 更新配置文件
     */
    public function update($save_path = '')
    {
        $config = $this->getConfig();

        if (empty($save_path)) $save_path = $this->config_path;

        return $this->expload($config['root_dir'], $save_path);
    }

    /**
     * 批量拉取代码
     */
    public function pull()
    {
        $config = $this->getConfig();

        foreach ($config['projects'] as $project) {
            $root_dir = $config['root_dir'];
            $gitTool = new GitTool($root_dir.'/'.$project['dir']);
            $branch = $gitTool->branch();

            $this->echolt("==== [ {$project['dir']} ] ====");
            foreach ($branch as $br) {
                $gitTool->checkout($br);

                $gitTool->pull();
            }
        }
    }

    /**
     * 批量推送代码
     */
    public function push()
    {
        $config = $this->getConfig();

        foreach ($config['projects'] as $project) {
            $root_dir = $config['root_dir'];
            $gitTool = new GitTool($root_dir.'/'.$project['dir']);
            $branch = $gitTool->branch();

            foreach ($branch as $br) {
                $gitTool->checkout($br);

                $gitTool->push();
            }
        }
    }

    protected function echolt($str, $exit=0)
    {
        if (! is_string($str)) {
            $str = json_encode($str, JSON_UNESCAPED_UNICODE);
        }

        echo $str."\n";

        if ($exit) exit;
    }
}


$manage = new Manage('./config/xjw.json');

$data = $manage->pull();
var_dump($data);
