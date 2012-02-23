echo "This script clones twitter bootsrap, compiles it with lessc and lessphp,"
echo "cleans up results with csstidy, and outputs diff. To run it, you need to"
echo "have git, csstidy and lessc installed."
echo ""

csstidy_params="--allow_html_in_templates=false --compress_colors=false
--compress_font-weight=false --discard_invalid_properties=false
--lowercase_s=false --preserve_css=true --remove_bslash=false
--remove_last_;=false --sort-properties=true --sort-selectors=true
--timestamp=false --silent=true --merge_selectors=0 --case-properties=0
--optimize-shorthands=0 --template=high"

if [ -z "$@" ]; then
  diff_tool="diff -b -u -t -B"
else
  diff_tool=$@
fi

mkdir -p tmp

if [ ! -d 'bootstrap/' ]; then
  echo ">> Cloning bootstrap to bootstrap/"
  git clone https://github.com/twitter/bootstrap
fi

echo ">> Lessc compilation"
lessc bootstrap/less/bootstrap.less tmp/bootstrap.lessc.css

echo ">> Lessphp compilation"
../plessc bootstrap/less/bootstrap.less tmp/bootstrap.lessphp.css
echo ">> Cleanup and convert"

# csstidy tmp/bootstrap.lessc.css $csstidy_params tmp/bootstrap.lessc.clean.css
# csstidy tmp/bootstrap.lessphp.css $csstidy_params tmp/bootstrap.lessphp.clean.css
#
# # put a newline after { and :
# function split() {
#   sed 's/\(;\|{\)/\1\n/g'
# }
#
# # csstidy is messed up and wont output to stdout when there are a bunch of options
# cat tmp/bootstrap.lessc.clean.css | split | tee tmp/bootstrap.lessc.clean.css
# cat tmp/bootstrap.lessphp.clean.css | split | tee tmp/bootstrap.lessphp.clean.css

php sort.php tmp/bootstrap.lessc.css > tmp/bootstrap.lessc.clean.css
php sort.php tmp/bootstrap.lessphp.css > tmp/bootstrap.lessphp.clean.css

echo ">> Doing diff"
$diff_tool tmp/bootstrap.lessc.clean.css tmp/bootstrap.lessphp.clean.css
