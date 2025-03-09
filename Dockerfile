FROM i386/debian

# Install dependencies
RUN apt-get update -o Acquire::Check-Valid-Until=false && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
    wget curl unzip libc6 libstdc++6 gcc make ca-certificates && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Create user
RUN groupadd -r hlds && useradd --no-log-init --system --create-home --home-dir /server --gid hlds hlds
USER hlds

# Install Metamod (Ensure directory exists first)
RUN mkdir -p /server/hlds_l/cstrike/addons/metamod/ && \
    curl -L -o /tmp/all_in_one_3.2a.zip https://archive.org/download/hlds_l_3111_full_202503/all_in_one_3.2a.zip && \
    unzip -o /tmp/all_in_one_3.2a.zip -d /server/hlds_l/ && \
    rm /tmp/all_in_one_3.2a.zip

# Install Podbot (Ensure correct extraction path)
RUN mkdir -p /server/hlds_l/cstrike/addons/podbot/ && \
    curl -L -o /tmp/podbot_full_V3B22.zip https://archive.org/download/hlds_l_3111_full_202503/podbot_full_V3B22.zip && \
    unzip -o /tmp/podbot_full_V3B22.zip -d /server/hlds_l/cstrike/addons/ && \
    rm /tmp/podbot_full_V3B22.zip
	
RUN mkdir -p /server/hlds_l/cstrk15/addons/
RUN cp -r -f /server/hlds_l/cstrike/addons /server/hlds_l/cstrk15/

# Download and extract HLDS
RUN curl -L -o /tmp/hlds_l_3109_full.tar.gz https://archive.org/download/hlds_l_3111_full/hlds_l_3109_full.tar.gz && \
    mkdir -p /server/hlds_l/ && \
    tar -xzf /tmp/hlds_l_3109_full.tar.gz -C /server/ && \
    rm /tmp/hlds_l_3109_full.tar.gz
	
# Download and install Counter-Strike 1.5
RUN curl -L -o /tmp/cs_15_full.tar.gz https://archive.org/download/hlds_l_3111_full_202503/cs_15_full.tar.gz && \
    tar -xzf /tmp/cs_15_full.tar.gz -C /server/hlds_l/cstrk15 && \
	mv -f //server/hlds_l/cstrk15/cstrike/* /server/hlds_l/cstrk15/ && \
    rm /tmp/cs_15_full.tar.gz

# Download and install Counter-Strike 1.3
RUN curl -L -o /tmp/cs_13_full.tar.gz https://archive.org/download/hlds_l_3111_full_202503/cs_13_full.tar.gz && \
    tar -xzf /tmp/cs_13_full.tar.gz -C /server/hlds_l/ && \
    rm /tmp/cs_13_full.tar.gz



# Remove unnecessary mod folders
RUN rm -rf /server/hlds_l/tfc /server/hlds_l/dmc /server/hlds_l/ricochet  /server/hlds_l/cstrk15/cstrike

WORKDIR /server/hlds_l/

# Install WON2Fixes and modified HLDS_RUN
USER root
# Create and overwrite woncomm.lst & valvecomm.lst
# Create and overwrite woncomm.lst & valvecomm.lst
RUN echo "\
Titan\n\
{\n\
\ttitan.won2.steamlessproject.nl:6003\n\
\tcs.techpinoy.net:6003\n\
}\n\
\n\
Auth\n\
{\n\
\t// As for now (Oct 2005) this server isn't online yet.\n\
\tauth.won2.steamlessproject.nl:7002\n\
}\n\
\n\
Master\n\
{\n\
\tmaster.won2.steamlessproject.nl:27010\n\
\tmaster4.won2.steamlessproject.nl:27010\n\
\tmaster2.won2.steamlessproject.nl:27010\n\
\tmaster3.won2.steamlessproject.nl:27010\n\
\thlmaster.order-of-phalanx.net:27010\n\
\thlmaster2.order-of-phalanx.net:27010\n\
\thlmaster3.order-of-phalanx.net:27010\n\
\tcs.techpinoy.net:27010\n\
}\n\
\n\
ModServer\n\
{\n\
\tmods.won2.steamlessproject.nl:27011\n\
\tcs.techpinoy.net:27011\n\
}\n\
\n\
Secure\n\
{\n\
\thlauth.won2.steamlessproject.nl:27012\n\
\thlauth2.won2.steamlessproject.nl:27012\n\
\thlauth3.won2.steamlessproject.nl:27012\n\
}" | tee /server/hlds_l/valve/woncomm.lst /server/hlds_l/valve/valvecomm.lst > /dev/null


COPY config ./
COPY config/cstrike ./cstrk15/
RUN chmod +x /server/hlds_l/hlds*

# Modify HLDS_RUN for WON2
RUN echo 'int NET_IsReservedAdr(){return 1;}' > /server/hlds_l/nowon.c && \
    gcc -fPIC -c /server/hlds_l/nowon.c -o /server/hlds_l/nowon.o && \
    ld -shared -o /server/hlds_l/nowon.so /server/hlds_l/nowon.o && \
    rm -f /server/hlds_l/nowon.c /server/hlds_l/nowon.o

# Modify hlds_run to include LD_PRELOAD
RUN sed -i '/^export /a export LD_PRELOAD="nowon.so"' /server/hlds_l/hlds_run
RUN touch /server/hlds_l/cstrike/server.cfg /server/hlds_l/cstrike/listenserver.cfg && \
    chmod 666 /server/hlds_l/cstrike/server.cfg /server/hlds_l/cstrike/listenserver.cfg && \
    sed -i '/\/\/hostname/a\hostname "PASAY 24/7 CS 1.3"' /server/hlds_l/cstrike/listenserver.cfg \
    && sed -i '/\/\/hostname/a\hostname "PASAY 24/7 CS 1.3"' /server/hlds_l/cstrike/server.cfg	

RUN touch /server/hlds_l/cstrk15/server.cfg /server/hlds_l/cstrk15/listenserver.cfg && \
    chmod 666 /server/hlds_l/cstrk15/server.cfg /server/hlds_l/cstrk15/listenserver.cfg && \
    sed -i '/\/\/hostname/a\hostname "PASAY 24/7 CS 1.5"' /server/hlds_l/cstrk15/listenserver.cfg \
    && sed -i '/\/\/hostname/a\hostname "PASAY 24/7 CS 1.5"' /server/hlds_l/cstrk15/server.cfg	
    
USER hlds

ENV TERM xterm

ENTRYPOINT ["./hlds_run"]
CMD ["-game", "cstrike", "+map", "de_dust2", "+maxplayers", "16"]
