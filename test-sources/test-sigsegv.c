int main()
{
  int a[10];
  int *b;

  b = 10;
  *b = a[-1000000];

  return 0;
}
