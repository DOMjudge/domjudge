#include <unistd.h>

#define SECS_PER_DAY   (60 * 60 *24)

int main(void)
{
	while(1)
		sleep(1 * SECS_PER_DAY);

	return 0;
}
