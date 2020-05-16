# This is used instead of substitutions.py when generating the manual from
# `make dist' when paths.mk is not yet available.

# Keep this in sync with substitutions.py.in.

rst_prolog = """
.. |DOMjudge| replace:: DOMjudge

.. |baseurlteam| replace:: https://example.com/domjudge/team

.. |SOURCESIZE| replace:: 256
.. |COMPILETIME| replace:: 30
.. |PROCLIMIT| replace:: 15

"""
