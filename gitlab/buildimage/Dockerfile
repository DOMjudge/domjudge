FROM domjudge/gitlabci:2.1

ARG DJ_SRC
ARG DJ_ETC

ENV DEBIAN_FRONTEND=noninteractive

# Use the current PR sources as if its a released version
RUN mkdir $DJ_SRC
COPY domjudge $DJ_SRC/
RUN chown -R domjudge $DJ_SRC

WORKDIR $DJ_SRC

RUN sudo -u domjudge ./gitlab/buildimage/configure.sh

# On start of container start some services
RUN cp -r ./gitlab/buildimage/scripts /
RUN ls -atrl /scripts

# Expose HTTP port
EXPOSE 80

# This is in default setup never used as we overrule
# the entrypoint in normal CI usage.
CMD ["/scripts/start.sh"]
