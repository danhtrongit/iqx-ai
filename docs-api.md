# Create chat completion (streaming)

## OpenAPI Specification

```yaml
openapi: 3.0.1
info:
  title: ''
  description: ''
  version: 1.0.0
paths:
  /v1/chat/completions:
    post:
      summary: Create chat completion (streaming)
      deprecated: false
      description: >-
        Given a hint, the model will return one or more predicted completions
        and can also return the probability of the surrogate marker for each
        position.


        Complete creation for the provided prompts and parameter
      tags:
        - Chat Models/ChatGpt/ChatGPT (Chat)
      parameters:
        - name: Content-Type
          in: header
          description: ''
          required: true
          example: application/json
          schema:
            type: string
        - name: Accept
          in: header
          description: ''
          required: true
          example: application/json
          schema:
            type: string
        - name: Authorization
          in: header
          description: ''
          required: false
          example: Bearer {{YOUR_API_KEY}}
          schema:
            type: string
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                model:
                  type: string
                  description: >
                    The ID of the model to use. For more information about which
                    models can be used with the Chat API, see the model endpoint
                    compatibility table.
                messages:
                  type: array
                  items:
                    type: object
                    properties:
                      role:
                        type: string
                      content:
                        type: string
                    x-apifox-orders:
                      - role
                      - content
                  description: >-
                    List of messages contained in the conversation so far.
                    Python code examples.
                temperature:
                  type: integer
                  description: >-
                    What sampling temperature to use, between 0 and 2. A higher
                    value (like 0.8) will make the output more random, while a
                    lower value (like 0.2) will make the output more focused and
                    deterministic.  We generally recommend changing this or
                    top_p but not both.
                top_p:
                  type: integer
                  description: >-
                    An alternative to temperature sampling, called kernel
                    sampling, where the model considers the results of markers
                    with top_p probability mass. So 0.1 means only the tokens
                    that make up the top 10% of probability mass are
                    considered.  We generally recommend changing this or
                    `temperature` but not both.
                'n':
                  type: integer
                  description: >-
                    Default is 1

                    How many chat completion choices are generated for each
                    input message.
                stream:
                  type: boolean
                  description: >-
                    Defaults to false If set, partial message deltas will be
                    sent like in ChatGPT. Markers will be sent in the form of
                    data-only server-sent events when available, and on a data:
                    [DONE] message terminating the stream. Python code examples.
                stop:
                  type: string
                  description: >-
                    Defaults to null for up to 4 sequences and the API will stop
                    generating further tokens.
                max_tokens:
                  type: integer
                  description: >-
                    Default is inf

                    The maximum number of tokens generated in chat completion.


                    The total length of input tokens and generated tokens is
                    limited by the context length of the model. Python code
                    example for calculating tokens
                presence_penalty:
                  type: number
                  description: >-
                    A number between -2.0 and 2.0. Positive values ​​penalize
                    new tokens based on whether they have appeared in the text
                    so far, thus increasing the likelihood that the model is
                    talking about new topics.  [See more information on
                    frequency and presence penalties.
                    ](https://platform.openai.com/docs/api-reference/parameter-details)
                frequency_penalty:
                  type: number
                  description: >-
                    Defaults to 0 -a number between 2.0 and 2.0. Positive values
                    ​​penalize new tokens based on how frequently the text
                    currently exists, reducing the likelihood that the model
                    will repeat the same line.  More information on frequency
                    and presence penalties.
                logit_bias:
                  type: 'null'
                  description: >-
                    Modifies the likelihood that the specified tag appears in
                    completion.


                    Accepts a JSON object that maps tags (tag IDs specified by
                    the tagger) to associated bias values ​​(-100 to 100).
                    Mathematically speaking, the bias is added to the logit
                    generated by the model before sampling the model. The exact
                    effect varies between models, but values ​​between -1 and 1
                    should reduce or increase the selection likelihood of the
                    relevant marker; values ​​such as -100 or 100 should result
                    in disabled or exclusive selection of the relevant marker.
                user:
                  type: string
                  description: >-
                    A unique identifier that represents your end user and helps
                    OpenAI monitor and detect abuse. [Learn
                    more](https://platform.openai.com/docs/guides/safety-best-practices/end-user-ids).
                response_format:
                  type: object
                  properties: {}
                  x-apifox-orders: []
                  description: >-
                    An object that specifies the format in which the model must
                    be output.  Setting { "type": "json_object" } enables JSON
                    mode, which ensures that messages generated by the model are
                    valid JSON.  Important: When using JSON schema, you must
                    also instruct the model to generate JSON via a system or
                    user message. If you don't do this, the model may generate
                    an endless stream of blanks until the token limit is
                    reached, resulting in increased latency and the appearance
                    of a "stuck" request. Also note that if
                    finish_reason="length", the message content may be partially
                    cut off, indicating that the generation exceeded max_tokens
                    or the conversation exceeded the maximum context length. 
                    display properties
                seen:
                  type: integer
                  description: >-
                    This feature is in beta. If specified, our system will do
                    its best to sample deterministically so that repeated
                    requests with the same seed and parameters should return the
                    same results. Determinism is not guaranteed and you should
                    refer to the system_fingerprint response parameter to
                    monitor the backend for changes.
                tools:
                  type: array
                  items:
                    type: string
                  description: >-
                    A list of a set of tools that the model can call. Currently,
                    only functions that are tools are supported. Use this
                    feature to provide a list of functions for which the model
                    can generate JSON input.
                tool_choice:
                  type: object
                  properties: {}
                  description: >-
                    Controls which function (if any) the model calls. none means
                    that the model will not call the function, but generate a
                    message. auto means that the model can choose between
                    generating messages and calling functions. Force the model
                    to call the function via {"type": "function", "function":
                    {"name": "my_function"}}.  If no function exists, the
                    default is none. If a function exists, it defaults to auto. 
                    Show possible types
                  x-apifox-orders: []
              required:
                - model
                - messages
                - tools
                - tool_choice
              x-apifox-orders:
                - model
                - messages
                - temperature
                - top_p
                - 'n'
                - stream
                - stop
                - max_tokens
                - presence_penalty
                - frequency_penalty
                - logit_bias
                - user
                - response_format
                - seen
                - tools
                - tool_choice
            example:
              model: gpt-4o
              messages:
                - role: system
                  content: You are a helpful assistant.
                - role: user
                  content: Hello!
              stream: true
      responses:
        '200':
          description: ''
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  object:
                    type: string
                  created:
                    type: integer
                  choices:
                    type: array
                    items:
                      type: object
                      properties:
                        index:
                          type: integer
                        message:
                          type: object
                          properties:
                            role:
                              type: string
                            content:
                              type: string
                          required:
                            - role
                            - content
                          x-apifox-orders:
                            - role
                            - content
                        finish_reason:
                          type: string
                      x-apifox-orders:
                        - index
                        - message
                        - finish_reason
                  usage:
                    type: object
                    properties:
                      prompt_tokens:
                        type: integer
                      completion_tokens:
                        type: integer
                      total_tokens:
                        type: integer
                    required:
                      - prompt_tokens
                      - completion_tokens
                      - total_tokens
                    x-apifox-orders:
                      - prompt_tokens
                      - completion_tokens
                      - total_tokens
                required:
                  - id
                  - object
                  - created
                  - choices
                  - usage
                x-apifox-orders:
                  - id
                  - object
                  - created
                  - choices
                  - usage
              example:
                id: chatcmpl-123
                object: chat.completion
                created: 1677652288
                choices:
                  - index: 0
                    message:
                      role: assistant
                      content: |-


                        Hello there, how may I assist you today?
                    finish_reason: stop
                usage:
                  prompt_tokens: 9
                  completion_tokens: 12
                  total_tokens: 21
          headers: {}
          x-apifox-name: OK
      security: []
      x-apifox-folder: Chat Models/ChatGpt/ChatGPT (Chat)
      x-apifox-status: released
      x-run-in-apifox: https://app.apifox.com/web/project/5884425/apis/api-262294473-run
components:
  schemas: {}
  securitySchemes: {}
servers: []
security: []

```