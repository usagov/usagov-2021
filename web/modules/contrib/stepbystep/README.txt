By reusing existing forms and breaking them down into a sequence (or wizard)
of smaller steps, Step by Step is a way to reduce the power of Drupal's UI
down to one decision at a time. It was originally designed to walk absolute
beginner webmasters through the choices needed to get them up and running on
building their first websites. Soon after, it was put to use creating a
workflow for some complex tasks that site admins need to get right without
having to memorise all the steps. You can use it to make a wizard for any
workflow that requires one or more forms from core, contrib or your own custom
modules.

A Step by Step sequence consists of a number of existing Drupal forms, which
are each to be displayed in part or in whole as one step in the sequence, with
added buttons allowing each one to be saved and marked as 'completed', or
marked as 'skipped' over without saving. It is possible for a form to be used
in more than one step, with a different part of the form visible each time.

Step by Step sequences are defined as plugins implementing SequenceInterface.
To create a custom wizard, create a subclass of SequenceBase, annotated with
@Sequence, and override getSteps() to define the steps in the sequence.
An example wizard is provided in examples/stepbystep_simplify.

This module was presented at NWDUG Unconference 2016 and Drupal Mountain Camp
2017 under the working title of "Simplestep".
