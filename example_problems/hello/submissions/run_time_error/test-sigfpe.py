'''
This should fail with RUN-ERROR due to integer division by zero.

@EXPECTED_RESULTS@: RUN-ERROR
'''

a = 0
b = 10 / a

print(b)
