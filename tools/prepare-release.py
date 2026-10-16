"""Validate an installable package before publishing to WordPress.org."""

import argparse
import os
from pathlib import Path, PurePosixPath
import re
import stat
import zipfile


def prepare(package: Path, output: Path, tag: str = '') -> str:
    allowed = {'mrn-form.php', 'readme.txt', 'LICENSE', 'uninstall.php', 'includes', 'assets'}
    forbidden = {'.git', '.github', 'docs', 'tests', 'tools', 'vendor', 'node_modules'}
    required = {'mrn-form.php', 'readme.txt', 'LICENSE', 'uninstall.php'}
    with zipfile.ZipFile(package) as archive:
        files = {}
        for entry in archive.infolist():
            path = PurePosixPath(entry.filename)
            if (path.is_absolute() or '\\' in entry.filename or '..' in path.parts
                    or len(path.parts) < 2 or path.parts[0] != 'mrn-forms'
                    or path.parts[1] not in allowed or set(path.parts) & forbidden
                    or stat.S_ISLNK(entry.external_attr >> 16)):
                raise ValueError('Unexpected package entry: ' + entry.filename)
            if not entry.is_dir():
                relative = PurePosixPath(*path.parts[1:])
                if relative in files:
                    raise ValueError('Duplicate package entry: ' + str(relative))
                files[relative] = archive.read(entry)
        if not required.issubset({str(path) for path in files}):
            raise ValueError('Required runtime files are missing.')

    header = files[PurePosixPath('mrn-form.php')].decode('utf-8-sig')
    readme = files[PurePosixPath('readme.txt')].decode('utf-8-sig')
    version_match = re.search(r'(?m)^\s*\* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$', header)
    if not version_match:
        raise ValueError('Plugin header must declare a three-part version.')
    version = version_match.group(1)
    if tag and (not re.fullmatch(r'v?[0-9]+\.[0-9]+\.[0-9]+', tag) or tag.removeprefix('v') != version):
        raise ValueError('Release tag must match the plugin version: ' + version)
    if not re.search(r"define\(\s*'MRNF_VERSION',\s*'" + re.escape(version) + r"'\s*\)", header):
        raise ValueError('MRNF_VERSION must match the plugin header.')
    if not re.search(r'(?m)^Stable tag:\s*' + re.escape(version) + r'\s*$', readme):
        raise ValueError('Stable tag must match the plugin header.')
    if not re.search(r'(?m)^\s*\* Text Domain:\s*mrn-forms\s*$', header):
        raise ValueError('Text domain must be mrn-forms.')
    contributors = re.search(r'(?m)^Contributors:\s*(.+)$', readme)
    if not contributors or 'mrnwp' not in [name.strip() for name in contributors.group(1).split(',')]:
        raise ValueError('Contributors must include the WordPress.org owner mrnwp.')
    if output.exists() and (not output.is_dir() or any(output.iterdir())):
        raise ValueError('Release output must be an empty directory.')

    output.mkdir(parents=True, exist_ok=True)
    for relative, content in files.items():
        destination = output.joinpath(*relative.parts)
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_bytes(content)
    return version


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--tag', default='')
    args = parser.parse_args()
    try:
        release_version = prepare(args.package, args.output, args.tag)
    except (ValueError, OSError, zipfile.BadZipFile) as error:
        parser.exit(1, str(error) + '\n')
    if os.environ.get('GITHUB_OUTPUT'):
        with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as handle:
            handle.write('version=' + release_version + '\n')
            handle.write('build_dir=' + str(args.output.resolve()) + '\n')
    print('Prepared MRN Forms ' + release_version)
