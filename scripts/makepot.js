const { exec } = require('child_process');
const path = require('path');

const isWindows = process.platform === 'win32';
const wpCliPath = isWindows
  ? 'vendor\\wp-cli\\wp-cli\\bin\\wp.bat'
  : 'vendor/wp-cli/wp-cli/bin/wp';

const cmd = `${wpCliPath} i18n make-pot ./ lang/multisite-ultimate.pot --slug=multisite-ultimate --exclude=node_modules,tests,docs,assets/js/lib`;

exec(cmd, (error, stdout, stderr) => {
  if (error) {
    console.error(`Error: ${error.message}`);
    process.exit(1);
  }
  if (stderr) {
    console.error(`stderr: ${stderr}`);
  }
  console.log(stdout);
});
