/*
 * Output answers slowly, should give TIMELIMIT with some output
 *
 * @EXPECTED_RESULTS@: TIMELIMIT
 */

#include <iostream>

int main()
{
	int i,j,k,l,x;

	for(i=10; 1; i+=10) {
		x = 0;
		for(j=0; j<i; j++)
			for(k=0; k<i; k++)
				for(l=0; l<i; l++)
					x++;

		std::cout << i << " ^ 3 = " << x << std::endl;
	}

	return 0;
}
