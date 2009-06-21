echo "Running aclocal..." ; aclocal -I m4 || exit 1
echo "Running autoheader..." ; autoheader || exit 
echo "Running autoconf..." ; autoconf || exit 1

./configure "$@"
