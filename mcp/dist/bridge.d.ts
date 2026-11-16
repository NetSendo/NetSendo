#!/usr/bin/env node
/**
 * NetSendo MCP - in-app tool bridge
 *
 * The NetSendo application serves a remote MCP endpoint (the "connector") and
 * runs the tools of this package through this file, so the tool list exists in
 * one place only. NetSendo starts `node bridge.js`, writes one command to stdin
 * and reads JSON lines from stdout:
 *
 *   <- {"op":"manifest"}                           list every tool
 *   <- {"op":"call","name":"…","arguments":{…}}    run one tool
 *
 *   -> {"type":"api","id":1,"method":"get","path":"/lists","params":{…},"data":{…}}
 *   <- {"type":"api_response","id":1,"status":200,"body":{…}}
 *   -> {"type":"result","result":{…}}              final answer, then exit
 *   -> {"type":"error","message":"…"}              the bridge itself failed
 *
 * API calls are not sent over the network: each one is handed back to the
 * application, which runs it as the signed-in connector user and enforces the
 * granted permissions. The bridge never holds a credential.
 */
export {};
//# sourceMappingURL=bridge.d.ts.map