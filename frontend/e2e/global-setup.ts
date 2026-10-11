import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function globalSetup() {
  const rootDir = path.resolve(__dirname, '../../');
  console.log('Running E2E global database setup via bin/e2e-seed.php...');
  execSync('php bin/e2e-seed.php', {
    cwd: rootDir,
    stdio: 'inherit',
  });
}

export default globalSetup;
