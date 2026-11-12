import { Context, Controller } from '@hotwired/stimulus';

/**
 * A Stimulus controller class with the properties Stimulus adds from its
 * targets and values, which a type can't infer from the static arrays.
 */
export type ControllerWith<Properties, ElementType extends Element = Element> = Omit<typeof Controller, 'prototype'> & {
    new (context: Context): Controller<ElementType> & Properties;
};
