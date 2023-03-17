/**
 * cli that will spawn child processes that run other scripts. scripts
 * that are run by this script-runner should exit with code 0 to signify
 * that this script-runner should continue or exit with code 2 to signify
 * that the script has completed successfully. Any other code is considered
 * an error. Scripts exit with code 0 by default.
 * 
 * @example:
 * node script-runner \
 *  -s script-to-run.js \
 *  -p "-a argument-value -b another-argument-value" \ // passthrough arguments
 *  -d 1000 \ // delay between recalls
 *  -m parallel \ // parallel or sequential
 *  -n 3 \ // number of parallel processes
 *  -l true // output logs to a file (named same as script)
 */
const {
    spawn
  } = require('child_process');
  const fs = require('fs');
  const path = require('path');
  const logger = require('idp-libs/Logger');
  const program = require('commander');
  
  // set program directives and establish help file
  program
    .version('0.1.0')
    .option('-s, --script <script>', 'path to the script relative to cwd')
    .option('-p, --props <props>', '[optional] arguments to be sent to the script')
    .option('-d, --delay <delay>', '[optional] delay between script executions')
    .option('-m, --mode <mode>', '[default: sequential] switch to parallel mode to run executions at the same time')
    .option('-n, --num <num>', '[default: 2] number of parallel processes')
    .option('-l, --logs', '[default: false] enabled or disabled logfile output')
    .parse(process.argv);
  
  // get the arguments from commander
  let {
    script,
    props,
    delay,
    mode,
    num,
    logs
  } = program;
  
  // check that a script was provided
  if (!script) {
    logger.error('script location must be provided with the -s switch');
    process.exit();
  }
  
  // add file logging
  if (logs) {
    let filename = `${path.basename(script, '.js')}.log`;
    logger.info(`logging output to ${filename}`);
  }
  
  // add .js to the script if it is not there
  if (!script.match(/\.js$/)) {
    script = `${script}.js`;
  }
  
  // make sure the script actually exists
  if (!fs.existsSync(script)) {
    logger.error(`script could not be found at ${__dirname}/${script}`);
    process.exit();
  }
  
  if (num) {
    // run in parallel
    num = num || 2;
    for (let n = 1; n <= num; n++) {
      buildProcess(script, props, {
        children: num,
        childId: n
      });
    }
  } else {
    // run sequential
    buildProcess(script, props);
  }
  
  /**
   * Start a child process.
   * 
   * @param {String} scriptPath dynamoDb documentClient service object
   * @param {String} props arguments to send to script
   */
  function buildProcess(scriptPath, props, parallelProps) {
    logger.info(`Running: ${scriptPath}`);
  
    // build the command string starting with the script name
    let commands = [scriptPath];
  
    // add props to the command array (if provided)
    if (props) commands.push(...props.split(' '));
  
    // add parallel params to command array (if provided)
    if (parallelProps) {
      commands.push('-c', parallelProps.children, '-i', parallelProps.childId);
    }
  
    const child = spawn('node', commands);
  
    child.stdout.on('data', (data) => {
      logger.info(`${scriptPath} output: \n\t${data}`);
      if(program.logs) fs.appendFileSync(`${path.basename(scriptPath).replace('.js', '').replace(' ', '-')}.log`, `${data}\n`.replace('\n\n', '\n'));
    });
  
    child.stderr.on('data', (data) => {
      logger.warn(`${scriptPath} error: \n\t${data}`);
      if(program.logs) fs.appendFileSync(`${path.basename(scriptPath).replace('.js', '').replace(' ', '-')}.log`, `${data}\n`.replace('\n\n', '\n'));
    });
   
    child.on('exit', (code, signal) => {
      if (!code) {
        // continue execution
        setTimeout(() => buildProcess(scriptPath, props, parallelProps), delay);
        return;
      }
  
      // code 2 is unused by node and here it is used to signal the end of execution
      if (code === 2) {
        logger.info(`Completed Execution: exit code: ${code}, signal: ${signal}`);
        return;
      }
  
      logger.error(`Execution was halted: exit code: ${code}, signal: ${signal}`);
    });
  }