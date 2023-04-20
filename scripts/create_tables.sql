/*
    * create tables for the database
    * Mariadb 10.1.26
    * database: ebemaWebhook
 */
-- create table repos (id, name, branch, path)
CREATE TABLE IF NOT EXISTS repos (
    ID int(10) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(250),
    branch varchar(250),
    path varchar(250),
    created timestamp default current_timestamp(),
    updated timestamp default current_timestamp(),
    PRIMARY KEY (ID),
    -- unique constraint on name and branch
    UNIQUE KEY repos_branch (name, branch)
);
-- create table logs (id, event, repo, branch, commit, commitName, commitUser, created)
CREATE TABLE IF NOT EXISTS logs (
    ID int(10) unsigned NOT NULL AUTO_INCREMENT,
    event varchar(250),
    repo varchar(250),
    branch varchar(250),
    commit varchar(250),
    commitName varchar(250),
    commitUser varchar(250),
    created timestamp default current_timestamp(),
    PRIMARY KEY (ID)
);
-- create table tokens (id, email, token, created)
CREATE TABLE IF NOT EXISTS tokens (
    ID int(10) unsigned NOT NULL AUTO_INCREMENT,
    email varchar(250),
    token varchar(64),
    created timestamp default current_timestamp(),
    updated timestamp default current_timestamp(),
    PRIMARY KEY (ID),
    UNIQUE KEY tokens_email (email)
);
-- create table authorized (id, type, text, created, updated)
CREATE TABLE IF NOT EXISTS authorized (
    ID int(10) unsigned NOT NULL AUTO_INCREMENT,
    type varchar(250), -- type of authorization (domain, email, etc)
    text varchar(250), -- the actual text of the authorization
    created timestamp default current_timestamp(),
    updated timestamp default current_timestamp(),
    PRIMARY KEY (ID),
    UNIQUE KEY authorized_type (type)
);

CREATE TRIGGER IF NOT EXISTS updated_tokens BEFORE UPDATE ON tokens 
FOR EACH ROW
BEGIN 
	SET NEW.updated = NOW();
END;
