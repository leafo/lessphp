echo "This script clones twitter bootsrap, compiles it with lessc and lessphp,"
echo "cleans up results with csstidy, and outputs diff. To run it, you need to"
echo "have git, csstidy and lessc installed."

csstidy_params="--allow_html_in_templates=false --compress_colors=false --compress_font-weight=false --discard_invalid_properties=false --lowercase_s=false --preserve_css=true --remove_bslash=false --remove_last\;=false --sort-properties=true --sort-selectors=true --timestamp=false --silent=true --merge_selectors=0 --case-properties=0 --optimize-shorthands=0 --template=high"

diff_params="-b -u -t -B"

if [ ! -d 'tmp/' ]
then
  mkdir tmp/
fi

if [ ! -d 'bootsrap/' ]
then
  echo ">> Cloning bootstrap to bootstrap/"
  git clone https://github.com/twitter/bootstrap
fi

echo ">> Lessc compilation"
lessc bootstrap/less/bootstrap.less tmp/bootstrap.lessc.css
echo ">> Lessphp compilation"
../plessc bootstrap/less/bootstrap.less tmp/bootstrap.lessphp.csstidy
echo ">> Cleanup and convert"
csstidy tmp/bootstrap.lessc.css $csstidy_params tmp/bootstrap.lessc.clean.css
csstidy tmp/bootstrap.lessphp.css $csstidy_params tmp/bootstrap.lessphp.clean.css
echo ">> Doing diff"
diff $diff_params tmp/bootstrap.lessc.clean.css tmp/bootstrap.lessphp.clean.css > tmp/diff
cat tmp/diff