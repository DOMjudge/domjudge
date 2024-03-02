#include <iostream>
#include <pthread.h>
#include <ctime>
#include <vector>
#include <chrono>
#include <thread>

double DESIRED_CPU_TIME;

void* thread_function(void*) {
    clock_t start_cpu_time = clock();
    double cpu_time_elapsed = 0.0;

    // This is just a busy loop...
    while (cpu_time_elapsed < DESIRED_CPU_TIME) {
        unsigned long long fib1 = 0, fib2 = 1, temp;
        for (int i = 0; i < 1000000; ++i) {
            temp = fib1;
            fib1 = fib2;
            fib2 += temp;
        }

        clock_t current_cpu_time = clock();
        cpu_time_elapsed = static_cast<double>(current_cpu_time - start_cpu_time) / CLOCKS_PER_SEC;
    }

    return nullptr;
}

int main(int argc, char**argv) {
    int NUM_THREADS = atoi(argv[1]);
    DESIRED_CPU_TIME = (double)(atoi(argv[2]));

    std::vector<pthread_t> threads(NUM_THREADS);
    for (int i = 0; i < NUM_THREADS; ++i) {
        pthread_create(&threads[i], nullptr, thread_function, nullptr);
    }
    for (int i = 0; i < NUM_THREADS; ++i) {
        pthread_join(threads[i], nullptr);
    }

    return 0;
}
